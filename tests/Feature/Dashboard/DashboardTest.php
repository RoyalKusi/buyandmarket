<?php

namespace Tests\Feature\Dashboard;

use App\Models\Address;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §14 Run 1.7 exit criterion: "Each role has a functional operational
 * home base." These tests drive the dashboard shell's actual write paths
 * (not just that a page renders) for every role.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TDD §8.2 / App\Http\Middleware\EnsureTwoFactorEnabled: seller and
     * admin dashboard routes require 2FA. Seller::factory() builds its
     * own User internally, so this is applied after the fact rather than
     * through a factory state chain.
     */
    private function enableTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function test_login_and_register_views_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_a_buyer_can_view_orders_and_manage_addresses(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($buyer)->create();

        $this->actingAs($buyer)->get('/dashboard')->assertOk()->assertSee($order->order_number);
        $this->actingAs($buyer)->get("/dashboard/orders/{$order->id}")->assertOk();

        $this->actingAs($buyer)->post('/dashboard/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Tinashe Moyo',
            'phone' => '0771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => '12 Sample Ave',
        ])->assertRedirect();

        $address = Address::where('user_id', $buyer->id)->firstOrFail();
        $this->actingAs($buyer)->get('/dashboard/addresses')->assertOk()->assertSee('Home');

        $this->actingAs($buyer)->delete("/dashboard/addresses/{$address->id}")->assertRedirect();
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_a_buyer_cannot_view_another_buyers_order(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $otherOrder = Order::factory()->for(User::factory()->withRole('buyer'))->create();

        $this->actingAs($buyer)->get("/dashboard/orders/{$otherOrder->id}")->assertForbidden();
    }

    public function test_a_seller_can_manage_products_orders_and_delivery(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $product = Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$product->id}/submit")
            ->assertRedirect();
        $this->assertSame('pending_review', $product->fresh()->status);

        $zone = DeliveryZone::factory()->create();
        $this->actingAs($seller->user)->post('/seller/dashboard/delivery', [
            'zone_id' => $zone->id,
            'method' => 'standard',
            'base_fee' => '3.50',
            'eta_min_days' => 2,
            'eta_max_days' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_rate_cards', [
            'seller_id' => $seller->id,
            'zone_id' => $zone->id,
            'method' => 'standard',
        ]);

        $order = Order::factory()->for(User::factory()->withRole('buyer'))->create(['status' => 'confirmed']);
        $orderGroup = OrderGroup::factory()->for($order)->for($seller)->create(['status' => 'confirmed']);

        $this->actingAs($seller->user)->post("/seller/dashboard/order-groups/{$orderGroup->id}/shipment", [
            'zone_id' => $zone->id,
            'method' => 'standard',
        ])->assertRedirect();

        $this->assertDatabaseHas('order_group_shipments', ['order_group_id' => $orderGroup->id]);
    }

    public function test_a_seller_can_create_a_product_with_variants_and_attributes_through_the_web_form(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);

        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['name' => 'Colour']);
        $value = AttributeValue::factory()->for($attribute)->create(['value' => 'Black']);
        $category->attributes()->attach($attribute->id, ['required' => false]);

        $this->actingAs($seller->user)
            ->get('/seller/dashboard/products/create')
            ->assertOk()
            ->assertSee('Create product')
            ->assertSee('Colour', false);

        $this->actingAs($seller->user)->post('/seller/dashboard/products', [
            'category_id' => $category->id,
            'title' => 'Bluetooth Speaker',
            'description' => 'Loud and portable.',
            'base_price' => '24.99',
            'variants' => [
                ['sku' => 'SKU-FORM-0001', 'stock_quantity' => 5, 'attribute_value_ids' => [$value->id]],
            ],
        ])->assertRedirect(route('seller.dashboard.products'));

        $this->assertDatabaseHas('products', ['title' => 'Bluetooth Speaker', 'status' => 'draft']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-FORM-0001']);
    }

    public function test_a_seller_can_get_a_text_seeded_category_and_attribute_suggestion(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);

        $category = Category::factory()->create(['name' => 'Bluetooth Speakers']);
        $colour = Attribute::factory()->create(['name' => 'Colour']);
        $black = AttributeValue::factory()->for($colour)->create(['value' => 'Black']);
        $category->attributes()->attach($colour->id, ['required' => false]);
        Category::factory()->create(['name' => 'Desk Lamps']);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => "Category: Bluetooth Speakers\nAttributes: Colour=Black"]]],
            ], 200),
        ]);

        $response = $this->actingAs($seller->user)->postJson('/seller/dashboard/products/suggest-categorization', [
            'title' => 'Waterproof Speaker',
            'bullets' => 'waterproof, black, 10h battery',
        ]);

        $response->assertOk();
        $response->assertJsonPath('category.id', $category->id);
        $response->assertJsonPath('attributes.0.value_id', $black->id);
    }

    public function test_a_seller_can_suggest_a_new_brand_from_the_product_creation_page(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);

        $this->actingAs($seller->user)
            ->post('/seller/dashboard/products/suggest-brand', ['name' => 'Acme Audio', 'slug' => 'acme-audio'])
            ->assertRedirect(route('seller.dashboard.products.create'));

        $this->assertDatabaseHas('brands', ['name' => 'Acme Audio', 'status' => 'pending', 'suggested_by_seller_id' => $seller->id]);
    }

    public function test_a_seller_without_a_store_is_redirected_to_finish_onboarding_before_creating_a_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending']);
        $this->enableTwoFactor($seller->user);

        $this->actingAs($seller->user)
            ->get('/seller/dashboard/products/create')
            ->assertRedirect(route('dashboard.become-seller'));
    }

    public function test_a_seller_cannot_manage_another_sellers_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $otherSeller = Seller::factory()->active()->create();
        $otherProduct = Product::factory()->for($otherSeller->store)->create(['status' => 'draft']);

        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$otherProduct->id}/submit")
            ->assertNotFound();
    }

    public function test_a_shipper_can_claim_and_progress_a_delivery(): void
    {
        Storage::fake('shipments');

        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $order = Order::factory()->for(User::factory()->withRole('buyer'))->create(['status' => 'confirmed']);
        $orderGroup = OrderGroup::factory()->for($order)->for($seller)->create(['status' => 'confirmed']);

        $this->actingAs($seller->user)->post("/seller/dashboard/order-groups/{$orderGroup->id}/shipment", [
            'zone_id' => $zone->id,
            'method' => 'standard',
        ])->assertRedirect();

        $shipment = $orderGroup->fresh()->shipment;

        $shipperUser = User::factory()->withRole('shipper')->create();
        $shipper = Shipper::factory()->for($shipperUser)->create();

        $this->actingAs($shipperUser)
            ->post("/shipper/dashboard/shipments/{$shipment->id}/claim")
            ->assertRedirect();

        $this->assertSame($shipper->id, $shipment->fresh()->shipper_id);

        $this->actingAs($shipperUser)->post("/shipper/dashboard/shipments/{$shipment->id}/events", [
            'event_type' => 'picked_up',
        ])->assertRedirect();

        $this->assertSame('picked_up', $shipment->fresh()->status);

        $this->actingAs($shipperUser)->post("/shipper/dashboard/shipments/{$shipment->id}/events", [
            'event_type' => 'delivered',
            'photo' => UploadedFile::fake()->image('proof.jpg'),
            'signature' => UploadedFile::fake()->image('signature.png'),
        ])->assertRedirect();

        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertSame('completed', $orderGroup->fresh()->status);
    }

    public function test_admin_can_approve_a_seller_and_a_product(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $this->enableTwoFactor($admin);

        $seller = Seller::factory()->create(['status' => 'under_review', 'kyc_status' => 'pending']);
        $this->actingAs($admin)
            ->post("/admin/dashboard/sellers/{$seller->id}/approve")
            ->assertRedirect();
        $this->assertSame('active', $seller->fresh()->status);

        $activeSeller = Seller::factory()->active()->create();
        $product = Product::factory()->for($activeSeller->store)->create(['status' => 'pending_review']);
        $this->actingAs($admin)
            ->post("/admin/dashboard/products/{$product->id}/approve")
            ->assertRedirect();
        $this->assertSame('published', $product->fresh()->status);

        $this->actingAs($admin)->get('/admin/dashboard/audit-log')->assertOk()->assertSee('seller.kyc_approved');
    }

    public function test_admin_can_filter_the_audit_log(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $this->enableTwoFactor($admin);

        $seller = Seller::factory()->create(['status' => 'under_review', 'kyc_status' => 'pending']);
        $this->actingAs($admin)->post("/admin/dashboard/sellers/{$seller->id}/approve");

        $activeSeller = Seller::factory()->active()->create();
        $product = Product::factory()->for($activeSeller->store)->create(['status' => 'pending_review']);
        $this->actingAs($admin)->post("/admin/dashboard/products/{$product->id}/approve");

        $this->actingAs($admin)
            ->get('/admin/dashboard/audit-log?action=seller.kyc_approved')
            ->assertOk()
            ->assertSee("Seller#{$seller->id}")
            ->assertDontSee("Product#{$product->id}");

        $this->actingAs($admin)
            ->get('/admin/dashboard/audit-log?actor='.urlencode($admin->email))
            ->assertOk()
            ->assertSee("Seller#{$seller->id}");

        $this->actingAs($admin)
            ->get('/admin/dashboard/audit-log?from='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertSee('No audit entries match these filters.');
    }
}

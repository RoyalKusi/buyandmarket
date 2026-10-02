<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §3.1 module 4 / §3.4 module 23: the "become a seller" / "become a
 * shipper" web flows, flagged deferred since Run 1.7 (both were
 * API-only). Each step here reuses the same service the API controllers
 * call (App\Services\SellerOnboardingService /
 * App\Services\ShipperOnboardingService) — this is a presentation-layer
 * test, not a re-test of that service's own business rules (see
 * tests/Feature/Seller/OnboardingTest.php for those).
 */
class SelfServiceOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('kyc');
    }

    /**
     * TDD §8.2 / App\Http\Middleware\EnsureTwoFactorEnabled: every
     * seller.dashboard.* route (including product creation) requires
     * 2FA, which bites mid-stepper here since registering as a seller
     * grants that role immediately.
     */
    private function enableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();
    }

    public function test_a_buyer_can_become_a_seller_through_the_full_web_stepper(): void
    {
        $user = User::factory()->withRole('buyer')->create();

        $this->actingAs($user)->get('/dashboard/become-seller')->assertOk()->assertSee('Create seller account');

        $this->actingAs($user)
            ->post('/dashboard/become-seller', ['business_name' => "Tino's Electronics"])
            ->assertRedirect('/dashboard/become-seller');

        $this->assertTrue($user->fresh()->hasRole('seller'));
        $seller = $user->fresh()->seller;

        // SellerPolicy::register() already accessed (and cached, as
        // null) $user->seller before the Seller row existed — each step
        // below needs a fresh User instance so that relation re-resolves
        // (same lesson as tests/Feature/Seller/OnboardingTest.php).
        $this->actingAs($user->fresh())
            ->post('/dashboard/become-seller/kyc-documents', [
                'national_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
                'proof_of_address' => UploadedFile::fake()->create('address.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/dashboard/become-seller');

        $this->actingAs($user->fresh())
            ->post('/dashboard/become-seller/payout-details', [
                'bank_name' => 'CBZ Bank',
                'account_name' => 'Tino Moyo',
                'account_number' => '0123456789',
            ])
            ->assertRedirect('/dashboard/become-seller');

        $this->actingAs($user->fresh())
            ->post('/dashboard/become-seller/store', [
                'name' => "Tino's Electronics",
                'slug' => 'tinos-electronics-web',
            ])
            ->assertRedirect('/dashboard/become-seller');

        $this->assertNotNull($seller->fresh()->store);
        $this->enableTwoFactor($user);

        // Submitting before the first-product step is still rejected —
        // the web form reuses the same rule the API enforces.
        $this->actingAs($user->fresh())
            ->post('/dashboard/become-seller/submit-for-review')
            ->assertRedirect();
        $this->assertSame('pending', $seller->fresh()->status);

        $category = Category::factory()->create();
        $this->actingAs($user->fresh())->post('/seller/dashboard/products', [
            'category_id' => $category->id,
            'title' => 'Bluetooth Speaker',
            'base_price' => '19.99',
            'variants' => [['sku' => 'SKU-WEB-0001', 'stock_quantity' => 3]],
        ])->assertRedirect(route('seller.dashboard.products'));

        $this->actingAs($user->fresh())
            ->post('/dashboard/become-seller/submit-for-review')
            ->assertRedirect('/dashboard/become-seller');

        $this->assertSame('under_review', $seller->fresh()->status);
    }

    public function test_a_seller_cannot_register_twice(): void
    {
        $seller = Seller::factory()->create();

        $this->actingAs($seller->user)
            ->post('/dashboard/become-seller', ['business_name' => 'Again'])
            ->assertForbidden();
    }

    public function test_a_buyer_can_become_a_shipper(): void
    {
        $user = User::factory()->withRole('buyer')->create();

        $this->actingAs($user)->get('/dashboard/become-shipper')->assertOk()->assertSee('Become a shipper');

        $this->actingAs($user)
            ->post('/dashboard/become-shipper', ['business_name' => 'Tino Deliveries'])
            ->assertRedirect('/dashboard');

        $this->assertTrue($user->fresh()->hasRole('shipper'));
        $this->assertDatabaseHas('shippers', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_a_shipper_cannot_register_twice(): void
    {
        $user = User::factory()->withRole('buyer')->create();
        $this->actingAs($user)->post('/dashboard/become-shipper', []);

        $this->actingAs($user->fresh())
            ->post('/dashboard/become-shipper', [])
            ->assertForbidden();
    }
}

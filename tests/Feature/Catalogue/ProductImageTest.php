<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §3.2 module 7 / §5.8 image pipeline (Run 1.15): validation,
 * quality scoring, variant generation, alt-text — deferred whole since
 * Run 1.2, see docs/adr/0007 for the deliberately-narrower alt-text
 * scope (text-seeded, not vision-derived).
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_seller_can_upload_a_qualifying_image_and_variants_are_generated(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create();

        $response = $this->actingAs($seller->user)->post("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('speaker.jpg', 1200, 1200),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'processed');

        $image = ProductImage::firstOrFail();
        $this->assertTrue($image->is_primary);
        Storage::disk('public')->assertExists($image->disk_path);
        Storage::disk('public')->assertExists($image->variantPath('thumb'));
        Storage::disk('public')->assertExists($image->variantPath('large'));
    }

    public function test_an_image_below_the_minimum_resolution_is_rejected_but_kept_for_review(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create();

        $response = $this->actingAs($seller->user)->post("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'rejected');

        $image = ProductImage::firstOrFail();
        $this->assertNotNull($image->rejection_reason);
        $this->assertFalse($image->is_primary);
        Storage::disk('public')->assertMissing($image->variantPath('thumb'));
    }

    public function test_a_seller_cannot_upload_images_to_another_sellers_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $otherSeller = Seller::factory()->active()->create();
        $otherProduct = Product::factory()->for($otherSeller->store)->create();

        // App\Http\Middleware\ScopeQueriesToActingSeller (prepended before
        // SubstituteBindings) makes {product} route-model-binding 404 on
        // another seller's row before this controller's own authorize()
        // call ever runs — same behaviour already exercised in
        // tests/Feature/Ai/ListingAssistantTest.php.
        $this->actingAs($seller->user)
            ->post("/api/v1/seller/products/{$otherProduct->id}/images", [
                'image' => UploadedFile::fake()->image('speaker.jpg', 1200, 1200),
            ])
            ->assertNotFound();
    }

    public function test_a_seller_can_delete_an_image_and_a_remaining_one_becomes_primary(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create();

        $this->actingAs($seller->user)->post("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('one.jpg', 1200, 1200),
        ]);
        $this->actingAs($seller->user)->post("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('two.jpg', 1200, 1200),
        ]);

        $primary = ProductImage::where('is_primary', true)->firstOrFail();
        $other = ProductImage::where('is_primary', false)->firstOrFail();

        $this->actingAs($seller->user)
            ->delete("/api/v1/seller/products/{$product->id}/images/{$primary->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
        $this->assertTrue($other->fresh()->is_primary);
    }

    public function test_the_pdp_shows_the_uploaded_image(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->actingAs($seller->user)->post("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('speaker.jpg', 1200, 1200),
        ]);

        $image = ProductImage::firstOrFail();

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee($image->variantUrl('large'), false);
    }

    public function test_a_seller_can_generate_alt_text_for_an_image(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['title' => 'Bluetooth Speaker']);
        $image = ProductImage::factory()->for($product)->create();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'A black portable Bluetooth speaker.']]],
            ], 200),
        ]);

        $this->enableTwoFactor($seller->user);
        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$product->id}/images/{$image->id}/alt-text")
            ->assertRedirect();

        $this->assertSame('A black portable Bluetooth speaker.', $image->fresh()->alt_text);
    }

    private function enableTwoFactor($user): void
    {
        $user->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    }
}

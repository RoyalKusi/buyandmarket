<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found during a mobile-app visual QA pass: the product detail screen
 * silently dropped the seller/store name because
 * Api\V1\ProductController::show() never eager-loaded `store`, even
 * though the browse/search endpoint (ProductSearchController) does.
 * Nothing caught it before because no test asserted this endpoint's
 * response shape at all.
 */
class ProductShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_the_products_store(): void
    {
        $seller = Seller::factory()->active()->create();
        $seller->store->update(['name' => 'Harare Tech Store']);
        $product = Product::factory()->for($seller->store)->published()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertSame('Harare Tech Store', $response->json('data.store.name'));
    }
}

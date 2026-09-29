<?php

namespace Tests\Feature\Ai;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD §14 Run 1.11 exit criterion: "Sellers actively using AI-assisted
 * listing; admin AI dashboard populated with real metrics." This
 * sandbox has no network path to a real LLM API — every chat-completion
 * call is faked (Http::fake), same pattern as AssistantTest.
 */
class ListingAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChatContent(string $content): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
            ], 200),
        ]);
    }

    public function test_a_seller_can_generate_accept_and_discard_a_description_suggestion(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['description' => 'Original description.']);

        $this->fakeChatContent('A waterproof speaker with 10 hours of battery life and USB-C charging.');

        $this->actingAs($seller->user)->post("/seller/dashboard/products/{$product->id}/ai-description", [
            'bullets' => 'waterproof, 10h battery, USB-C',
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame('A waterproof speaker with 10 hours of battery life and USB-C charging.', $product->ai_suggested_description);
        $this->assertSame('Original description.', $product->description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.suggestion.generated']);

        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$product->id}/ai-description/accept")
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('A waterproof speaker with 10 hours of battery life and USB-C charging.', $product->description);
        $this->assertNull($product->ai_suggested_description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.suggestion.accepted']);
    }

    public function test_a_seller_can_discard_a_suggestion_without_changing_the_real_description(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['description' => 'Kept as-is.']);

        $this->fakeChatContent('A suggestion nobody asked to keep.');

        $this->actingAs($seller->user)->post("/seller/dashboard/products/{$product->id}/ai-description", [
            'bullets' => 'anything',
        ])->assertRedirect();

        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$product->id}/ai-description/discard")
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('Kept as-is.', $product->description);
        $this->assertNull($product->ai_suggested_description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.suggestion.discarded']);
    }

    public function test_a_seller_cannot_generate_a_suggestion_for_another_sellers_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $otherSeller = Seller::factory()->active()->create();
        $otherProduct = Product::factory()->for($otherSeller->store)->create();

        $this->actingAs($seller->user)
            ->post("/seller/dashboard/products/{$otherProduct->id}/ai-description", ['bullets' => 'x'])
            ->assertNotFound();
    }

    public function test_the_admin_ai_monitoring_dashboard_shows_real_counts(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create();

        $this->fakeChatContent('A short generated description.');
        $this->actingAs($seller->user)->post("/seller/dashboard/products/{$product->id}/ai-description", [
            'bullets' => 'notes',
        ]);
        $this->actingAs($seller->user)->post("/seller/dashboard/products/{$product->id}/ai-description/accept");

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)->get('/admin/dashboard/ai-monitoring')
            ->assertOk()
            ->assertSeeText('Suggestions generated')
            ->assertSeeText('1');
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\Ai\IngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD §14 Run 1.8 exit criterion: "Assistant answers grounded product/
 * order questions with citations in staging." This sandbox has no
 * network path to a real LLM/embeddings API, so every call is faked
 * (Http::fake) — the same pattern already used for Pesepay/Paynow.
 */
class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmbeddings(): void
    {
        // A fixed vector for every call: ingestion and retrieval queries
        // all embed to the same point, so cosine similarity is a
        // deterministic 1.0 — this exercises the retrieval/citation
        // plumbing without depending on a real model's semantics.
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => [1.0, 0.0, 0.0]]],
            ], 200),
        ]);
    }

    private function fakeChat(array $responses): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence($responses),
        ]);
    }

    private function chatMessage(?string $content, array $toolCalls = []): array
    {
        $message = ['role' => 'assistant', 'content' => $content];

        if ($toolCalls !== []) {
            $message['tool_calls'] = $toolCalls;
        }

        return ['choices' => [['message' => $message]]];
    }

    private function toolCall(string $id, string $name, array $arguments): array
    {
        return ['id' => $id, 'function' => ['name' => $name, 'arguments' => json_encode($arguments)]];
    }

    private function publishedProduct(): Product
    {
        $seller = Seller::factory()->active()->create();

        return Product::factory()->for($seller->store)->published()->create([
            'title' => 'Samsung Galaxy A15',
            'base_price' => '189.99',
        ]);
    }

    public function test_the_assistant_answers_a_grounded_product_question_with_citations(): void
    {
        $this->fakeEmbeddings();
        $product = $this->publishedProduct();
        app(IngestionService::class)->ingestProduct($product);

        $this->fakeChat([
            Http::response($this->chatMessage("It's $189.99."), 200),
        ]);

        $buyer = User::factory()->withRole('buyer')->create();
        $conversationId = $this->actingAs($buyer)->postJson('/api/v1/ai/conversations')->assertCreated()->json('data.id');

        $response = $this->actingAs($buyer)->postJson("/api/v1/ai/conversations/{$conversationId}/messages", [
            'content' => 'How much is the Samsung Galaxy A15?',
        ])->assertCreated();

        $response->assertJsonPath('data.citations.0.type', 'product');
        $response->assertJsonPath('data.citations.0.id', $product->id);
    }

    public function test_the_assistant_calls_a_read_tool_and_grounds_its_answer_in_the_result(): void
    {
        $this->fakeEmbeddings();
        $product = $this->publishedProduct();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 7]);
        app(IngestionService::class)->ingestProduct($product);

        $this->fakeChat([
            Http::response($this->chatMessage(null, [
                $this->toolCall('call_1', 'check_stock', ['variant_id' => $variant->id]),
            ]), 200),
            Http::response($this->chatMessage('Yes, 7 in stock.'), 200),
        ]);

        $buyer = User::factory()->withRole('buyer')->create();
        $conversationId = $this->actingAs($buyer)->postJson('/api/v1/ai/conversations')->json('data.id');

        $response = $this->actingAs($buyer)->postJson("/api/v1/ai/conversations/{$conversationId}/messages", [
            'content' => 'Is it in stock?',
        ])->assertCreated();

        $response->assertJsonPath('data.citations.0.type', 'product');
        $response->assertJsonPath('data.citations.0.id', $product->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.tool.check_stock']);

        $toolMessage = ConversationMessage::where('role', 'tool')->firstOrFail();
        $this->assertSame('check_stock', $toolMessage->tool_name);
        $this->assertStringContainsString('"stock_quantity":7', $toolMessage->content);
    }

    public function test_a_state_changing_tool_requires_confirmation_before_executing(): void
    {
        $this->fakeEmbeddings();
        $product = $this->publishedProduct();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $buyer = User::factory()->withRole('buyer')->create();

        // Both responses are queued upfront: Http::fake() calls don't
        // reliably stack (a later fake for the same URL can shadow an
        // earlier one's remaining sequence), so the confirm step's
        // response is queued here too, consumed only when that second
        // real HTTP call happens later in the test.
        $this->fakeChat([
            Http::response($this->chatMessage('Adding it to your cart.', [
                $this->toolCall('call_1', 'add_to_cart', ['variant_id' => $variant->id, 'quantity' => 1]),
            ]), 200),
            Http::response($this->chatMessage("Done — it's in your cart."), 200),
        ]);

        $conversationId = $this->actingAs($buyer)->postJson('/api/v1/ai/conversations')->json('data.id');

        $pending = $this->actingAs($buyer)->postJson("/api/v1/ai/conversations/{$conversationId}/messages", [
            'content' => 'Add the Samsung Galaxy A15 to my cart.',
        ])->assertCreated();

        $pending->assertJsonPath('data.requires_confirmation', true);
        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ai.tool.add_to_cart']);

        $messageId = $pending->json('data.id');

        $confirmed = $this->actingAs($buyer)
            ->postJson("/api/v1/ai/conversations/{$conversationId}/messages/{$messageId}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('cart_items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.tool.add_to_cart']);
        $this->assertTrue(ConversationMessage::find($messageId)->confirmed);
        $confirmed->assertJsonPath('data.content', "Done — it's in your cart.");
    }

    public function test_get_order_status_is_scoped_to_the_authenticated_buyer_never_a_model_supplied_id(): void
    {
        $this->fakeEmbeddings();
        $owner = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($owner)->create();

        $intruder = User::factory()->withRole('buyer')->create();

        $this->fakeChat([
            Http::response($this->chatMessage(null, [
                $this->toolCall('call_1', 'get_order_status', ['order_id' => $order->id]),
            ]), 200),
            Http::response($this->chatMessage("I can't find that order on your account."), 200),
        ]);

        $conversationId = $this->actingAs($intruder)->postJson('/api/v1/ai/conversations')->json('data.id');

        $this->actingAs($intruder)->postJson("/api/v1/ai/conversations/{$conversationId}/messages", [
            'content' => "What's the status of order {$order->id}?",
        ])->assertCreated();

        $toolMessage = ConversationMessage::where('role', 'tool')->firstOrFail();
        $this->assertSame(['found' => false], json_decode($toolMessage->content, true));
    }

    public function test_a_buyer_cannot_reach_another_users_conversation(): void
    {
        $owner = User::factory()->withRole('buyer')->create();
        $conversationId = $this->actingAs($owner)->postJson('/api/v1/ai/conversations')->json('data.id');

        $intruder = User::factory()->withRole('buyer')->create();

        $this->actingAs($intruder)
            ->postJson("/api/v1/ai/conversations/{$conversationId}/messages", ['content' => 'hi'])
            ->assertNotFound();
    }

    public function test_the_dashboard_assistant_page_renders(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();

        $this->actingAs($buyer)->get('/dashboard/assistant')->assertOk()->assertSee('Ask BM Assistant');
    }

    /**
     * Run 1.12: PDP's "Ask about this product" link was flagged deferred
     * since Run 1.4 — it's wired now, pre-grounding a fresh conversation
     * in the product's context rather than continuing whatever the buyer
     * was previously asking about.
     */
    public function test_asking_about_a_product_from_the_pdp_starts_a_grounded_conversation(): void
    {
        $product = $this->publishedProduct();
        $buyer = User::factory()->withRole('buyer')->create();

        $this->actingAs($buyer)->get("/dashboard/assistant?product={$product->id}")
            ->assertOk()
            ->assertSee("Discussing: {$product->title}");

        $conversation = $buyer->conversations()->firstOrFail();
        $this->assertSame('product', $conversation->context_type);
        $this->assertSame($product->id, $conversation->context_id);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\Ai\AssistantService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Second independent sweep finding (P1): AssistantService::
 * confirmAction() checked `$pending->requires_confirmation` /
 * `$pending->confirmed` on an in-memory model, outside any lock, before
 * starting its DB transaction — the same TOCTOU shape already fixed
 * once in OrderService during this audit's first sweep. Two
 * near-simultaneous confirm calls (a double-click, or a retried
 * request) could both pass the check and both execute the
 * state-changing tool, e.g. adding an item to the cart twice from one
 * buyer confirmation.
 */
class ConfirmActionRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_the_same_pending_action_twice_only_executes_it_once(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Added it to your cart.']]],
            ], 200),
        ]);

        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $assistant = app(AssistantService::class);
        $conversation = $assistant->startConversation($buyer, null);

        $pending = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Add this to your cart?',
            'requires_confirmation' => true,
            'confirmed' => false,
            'tool_calls' => [[
                'id' => 'call_1',
                'name' => 'add_to_cart',
                'arguments' => ['variant_id' => $variant->id, 'quantity' => 1],
            ]],
        ]);

        // Simulates two requests racing each other: both load their own
        // copy of the pending message while it still reads
        // confirmed=false, exactly as two near-simultaneous HTTP
        // requests each resolving their own ConversationMessage instance
        // would. The vulnerable code checked `$pending->confirmed` on
        // the in-memory object the caller already held, not a fresh,
        // locked read — so both copies would pass the guard.
        $copyA = ConversationMessage::find($pending->id);
        $copyB = ConversationMessage::find($pending->id);

        $assistant->confirmAction($copyA);

        $this->expectException(ValidationException::class);
        try {
            $assistant->confirmAction($copyB);
        } finally {
            $cart = app(CartService::class)->getOrCreateCart($buyer, null);
            $item = $cart->items()->where('variant_id', $variant->id)->first();

            $this->assertNotNull($item);
            $this->assertSame(1, $item->quantity, 'The add_to_cart tool executed more than once for a single confirmation.');
        }
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\AiAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit finding (P2, cost-abuse): App\Services\Ai\AssistantService::
 * sendMessage() had no rate limit despite being a real paid
 * LlmProvider call on every invocation — reachable unauthenticated via
 * the guest-session API group. Fixed inside sendMessage() itself (the
 * one choke point both the API route and the dashboard's Livewire
 * AiAssistant component funnel through), plus a route-level throttle
 * for defense in depth.
 */
class AssistantRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'A reply.']]],
            ], 200),
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => [1.0, 0.0, 0.0]]],
            ], 200),
        ]);
    }

    public function test_sending_too_many_messages_via_the_api_is_rejected(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $conversationId = $this->actingAs($buyer)->postJson('/api/v1/ai/conversations')->json('data.id');

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($buyer)
                ->postJson("/api/v1/ai/conversations/{$conversationId}/messages", ['content' => "message {$i}"])
                ->assertCreated();
        }

        $this->actingAs($buyer)
            ->postJson("/api/v1/ai/conversations/{$conversationId}/messages", ['content' => 'one too many'])
            ->assertStatus(422);
    }

    public function test_sending_too_many_messages_via_the_livewire_dashboard_widget_is_also_rejected(): void
    {
        // Proves the fix covers the Livewire path, not just the HTTP
        // route — a route-only throttle would have left this, the one
        // most buyers actually use, completely unprotected.
        $buyer = User::factory()->withRole('buyer')->create();

        $component = Livewire::actingAs($buyer)->test(AiAssistant::class);

        for ($i = 0; $i < 20; $i++) {
            $component->set('draft', "message {$i}")->call('send');
        }

        $component->set('draft', 'one too many')->call('send');

        $component->assertHasErrors('message');
    }

    public function test_each_conversation_owner_has_an_independent_limit(): void
    {
        $buyerA = User::factory()->withRole('buyer')->create();
        $buyerB = User::factory()->withRole('buyer')->create();

        $conversationA = $this->actingAs($buyerA)->postJson('/api/v1/ai/conversations')->json('data.id');
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($buyerA)->postJson("/api/v1/ai/conversations/{$conversationA}/messages", ['content' => "msg {$i}"]);
        }

        $conversationB = $this->actingAs($buyerB)->postJson('/api/v1/ai/conversations')->json('data.id');

        // Buyer A is now throttled; buyer B, a different key, is not.
        $this->actingAs($buyerB)
            ->postJson("/api/v1/ai/conversations/{$conversationB}/messages", ['content' => 'hello'])
            ->assertCreated();
    }
}

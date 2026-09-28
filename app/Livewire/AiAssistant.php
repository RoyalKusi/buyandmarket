<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\Ai\AssistantService;
use Livewire\Component;

/**
 * Design System §7.2: the conversation surface. This is a functional,
 * scoped-down v1 — the dark blue-950 panel treatment, streaming tokens,
 * quick-action chips and grounded product/store/order cards rendered as
 * their own components (§7.3) are deferred (see CHANGELOG.md); messages
 * render as plain text with a citation list and, for a pending
 * state-changing tool call, an explicit Confirm/Cancel action card
 * (§7.3's own hard rule: never executed without one).
 */
class AiAssistant extends Component
{
    public ?int $conversationId = null;

    public string $draft = '';

    public function mount(): void
    {
        $conversation = auth()->user()->conversations()->latest()->first()
            ?? app(AssistantService::class)->startConversation(auth()->user(), null);

        $this->conversationId = $conversation->id;
    }

    public function send(AssistantService $assistant): void
    {
        $content = trim($this->draft);

        if ($content === '') {
            return;
        }

        $this->draft = '';
        $assistant->sendMessage($this->conversation(), $content);
    }

    public function confirm(int $messageId, AssistantService $assistant): void
    {
        $message = $this->conversation()->messages()->findOrFail($messageId);
        $assistant->confirmAction($message);
    }

    public function cancel(int $messageId): void
    {
        $this->conversation()->messages()->whereKey($messageId)->update(['requires_confirmation' => false]);
    }

    private function conversation(): Conversation
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        abort_unless($conversation->user_id === auth()->id(), 404);

        return $conversation;
    }

    public function render()
    {
        return view('livewire.ai-assistant', [
            'messages' => $this->conversation()->messages()->get(),
        ]);
    }
}

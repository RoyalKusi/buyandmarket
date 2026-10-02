<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TDD §7.3: "Start/continue an AI assistant conversation" (Bearer or
 * guest session) / "Send a message; server executes retrieval + tool-
 * calling." Guest support mirrors the cart/checkout guest-session
 * pattern (Run 1.5) — same 'guest-session' middleware group.
 */
class ConversationController extends Controller
{
    public function store(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'context_type' => ['nullable', 'string', 'in:product,order'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $conversation = $assistant->startConversation(
            $request->user('sanctum'),
            $request->session()->getId(),
            $data['context_type'] ?? null,
            $data['context_id'] ?? null,
        );

        return response()->json(['data' => $conversation], 201);
    }

    public function storeMessage(Request $request, Conversation $conversation, AssistantService $assistant): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);

        $message = $assistant->sendMessage($conversation, $data['content']);

        return response()->json(['data' => $message], 201);
    }

    public function confirm(Request $request, Conversation $conversation, ConversationMessage $message, AssistantService $assistant): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $result = $assistant->confirmAction($message);

        return response()->json(['data' => $result]);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $owns = $request->user('sanctum') !== null
            ? $conversation->user_id === $request->user('sanctum')->id
            : $conversation->session_id === $request->session()->getId();

        abort_unless($owns, 404);
    }
}

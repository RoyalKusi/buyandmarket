<?php

namespace App\Services\Ai;

use App\Contracts\Ai\LlmCompletion;
use App\Contracts\Ai\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TDD §8.4/§15: "third-party LLM provider cost or availability volatility
 * ... tool-calling architecture and prompt/retrieval logic are
 * provider-agnostic at the integration boundary." This is one concrete
 * implementation of App\Contracts\Ai\LlmProvider, following the OpenAI
 * chat-completions wire shape most providers (and proxies in front of
 * others) mirror.
 *
 * This sandbox has no network path to a real LLM API to verify field
 * names against a live account — flagged the same way as PaynowGateway/
 * PesepayGateway, and for the same reason.
 */
class OpenAiCompatibleLlmProvider implements LlmProvider
{
    public function complete(array $messages, array $tools = []): LlmCompletion
    {
        $payload = ['model' => config('services.ai.chat_model'), 'messages' => $messages];

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => $tool,
            ], $tools);
        }

        $response = Http::withToken(config('services.ai.api_key'))
            ->post(config('services.ai.chat_url'), $payload);

        if ($response->failed()) {
            throw new RuntimeException('LLM provider request failed: '.$response->status());
        }

        $message = $response->json('choices.0.message') ?? [];

        $toolCalls = collect($message['tool_calls'] ?? [])
            ->map(fn (array $call) => [
                'id' => $call['id'],
                'name' => $call['function']['name'],
                'arguments' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
            ])
            ->all();

        return new LlmCompletion($message['content'] ?? null, $toolCalls);
    }
}

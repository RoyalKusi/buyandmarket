<?php

namespace App\Services\Ai;

use App\Contracts\Ai\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * See OpenAiCompatibleLlmProvider's doc comment — same provider-agnostic
 * boundary, same "unverified against a live API" caveat, for embeddings.
 */
class OpenAiCompatibleEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text): array
    {
        $response = Http::withToken(config('services.ai.api_key'))
            ->post(config('services.ai.embeddings_url'), [
                'model' => config('services.ai.embedding_model'),
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding provider request failed: '.$response->status());
        }

        return $response->json('data.0.embedding') ?? [];
    }
}

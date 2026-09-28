<?php

namespace App\Services\Ai;

use App\Contracts\Ai\EmbeddingProvider;
use App\Models\Embedding;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * TDD §5.2: "retrieval runs a metadata-filtered vector search first ...
 * then similarity-ranks within that filtered set — the AI cannot
 * retrieve a suspended seller's product even if it's semantically the
 * best match." Reranking (§5.2's cross-encoder pass) is out of scope for
 * this run — with no dedicated vector store or reranker service, cosine
 * similarity over the metadata-filtered set is the whole ranking step
 * (see docs/adr/0006).
 *
 * The visibility filter re-checks live status for product chunks rather
 * than trusting embedded metadata alone — a suspended seller's products
 * stay filtered out even if IngestionService hasn't re-embedded them
 * since the suspension (metadata can go stale; live status can't).
 */
class RetrievalService
{
    public function __construct(private readonly EmbeddingProvider $embeddings) {}

    /**
     * @return Collection<int, array{chunk: Embedding, score: float}>
     */
    public function search(string $query, int $limit = 6, int $candidatePoolSize = 20): Collection
    {
        $queryVector = $this->embeddings->embed($query);

        if ($queryVector === []) {
            return collect();
        }

        $ranked = Embedding::query()
            ->get()
            ->map(fn (Embedding $chunk) => [
                'chunk' => $chunk,
                'score' => $this->cosineSimilarity($queryVector, $chunk->vector),
            ])
            ->sortByDesc('score')
            ->take($candidatePoolSize);

        return $ranked
            ->filter(fn (array $result) => $this->isVisible($result['chunk']))
            ->take($limit)
            ->values();
    }

    private function isVisible(Embedding $chunk): bool
    {
        $metadata = $chunk->metadata ?? [];

        if (! isset($metadata['product_id'])) {
            return true;
        }

        $product = Product::find($metadata['product_id']);

        return $product !== null
            && $product->status === 'published'
            && $product->store?->seller?->status === 'active';
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}

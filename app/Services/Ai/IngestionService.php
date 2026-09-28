<?php

namespace App\Services\Ai;

use App\Contracts\Ai\EmbeddingProvider;
use App\Models\Product;
use App\Models\RagDocument;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * TDD §5.1: chunking strategy by source type. This run covers Product
 * (one chunk per product — variants summarised, not exploded per-variant)
 * and Store/seller profile; Policy/FAQ (CMS) and Promotion chunking are
 * deferred, since modules 44 and 14 don't exist yet to source them from
 * (see CHANGELOG.md).
 */
class IngestionService
{
    public function __construct(private readonly EmbeddingProvider $embeddings) {}

    public function ingestProduct(Product $product): void
    {
        $content = trim(implode("\n", array_filter([
            $product->title,
            $product->description,
            'Price: $'.number_format((float) $product->base_price, 2),
        ])));

        $this->ingest('product', $product->id, $content, [
            'product_id' => $product->id,
            'seller_id' => $product->store?->seller_id,
            'category_id' => $product->category_id,
            'in_stock' => $product->stock_quantity > 0,
            'status' => $product->status,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ]);
    }

    public function ingestStore(Store $store): void
    {
        $content = trim(implode("\n", array_filter([
            $store->name,
            $store->seller?->business_name,
        ])));

        $this->ingest('store', $store->id, $content, [
            'store_id' => $store->id,
            'seller_id' => $store->seller_id,
        ]);
    }

    private function ingest(string $sourceType, int $sourceId, string $content, array $metadata): void
    {
        if ($content === '') {
            return;
        }

        $hash = hash('sha256', $content);

        $document = RagDocument::firstOrNew(['source_type' => $sourceType, 'source_id' => $sourceId]);

        // TDD §5.1: "re-embedding triggered on content_hash change" —
        // skip the embedding-provider round trip entirely when nothing
        // about the source actually changed.
        if ($document->exists && $document->content_hash === $hash) {
            return;
        }

        DB::transaction(function () use ($document, $hash, $content, $metadata) {
            $document->content_hash = $hash;
            $document->save();

            $document->embeddings()->delete();
            $document->embeddings()->create([
                'chunk_index' => 0,
                'vector' => $this->embeddings->embed($content),
                'metadata' => [...$metadata, 'content' => $content],
            ]);
        });
    }
}

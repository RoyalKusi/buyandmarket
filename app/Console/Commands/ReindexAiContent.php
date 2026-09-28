<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Services\Ai\IngestionService;
use Illuminate\Console\Command;

/**
 * TDD §5.1: "full re-index nightly for slow-moving content ...
 * event-triggered incremental re-embedding on product create/update/
 * price-change/stock-change (queued job)." This run implements the
 * batch/nightly half only — event-triggered incremental re-embedding on
 * every product save is deferred (see CHANGELOG.md): wiring it in
 * without a queue worker guaranteed running would make routine product
 * saves synchronously dependent on an external embeddings API, which is
 * worse than a nightly batch for a launch topology that may not have
 * Redis/Horizon yet (§2.1).
 */
class ReindexAiContent extends Command
{
    protected $signature = 'ai:reindex';

    protected $description = 'Re-embed published products and stores for the AI assistant\'s retrieval index.';

    public function handle(IngestionService $ingestion): int
    {
        $products = Product::published()->get();
        $this->withProgressBar($products, fn (Product $product) => $ingestion->ingestProduct($product));
        $this->newLine();

        $stores = Store::all();
        $this->withProgressBar($stores, fn (Store $store) => $ingestion->ingestStore($store));
        $this->newLine();

        $this->info("Indexed {$products->count()} products and {$stores->count()} stores.");

        return self::SUCCESS;
    }
}

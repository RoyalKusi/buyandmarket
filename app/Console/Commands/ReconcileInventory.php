<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Console\Command;

/**
 * TDD §6.4 rule 2: "product_variants.stock_quantity is a cached/
 * materialised value recomputable from the ledger if drift is ever
 * detected (checksum job, nightly)."
 */
class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile';

    protected $description = 'Recompute every variant\'s stock_quantity from its inventory ledger';

    public function handle(InventoryService $inventoryService): int
    {
        $checked = 0;

        foreach (ProductVariant::query()->cursor() as $variant) {
            $inventoryService->reconcile($variant);
            $checked++;
        }

        $this->info("Reconciled stock for {$checked} variant(s).");

        return self::SUCCESS;
    }
}

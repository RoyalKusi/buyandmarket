<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * TDD §3.2 module 12 / §6.4 rule 2: inventory_ledger is the single source
 * of truth for stock. product_variants.stock_quantity (and the product's
 * own rollup) are materialised values, always written alongside a ledger
 * row in the same transaction — never mutated directly.
 */
class InventoryService
{
    /**
     * @param  int  $delta  Positive to add stock, negative to remove it.
     */
    public function adjustStock(
        ProductVariant $variant,
        int $delta,
        string $reasonCode,
        ?int $orderItemId = null,
    ): InventoryLedger {
        return DB::transaction(function () use ($variant, $delta, $reasonCode, $orderItemId) {
            $ledgerEntry = InventoryLedger::create([
                'variant_id' => $variant->id,
                'delta' => $delta,
                'reason_code' => $reasonCode,
                'order_item_id' => $orderItemId,
                'created_at' => now(),
            ]);

            $variant->increment('stock_quantity', $delta);

            $this->recomputeProductStock($variant->product);

            return $ledgerEntry;
        });
    }

    /**
     * TDD §6.4 rule 2: "recomputable from the ledger for audit/repair."
     * Recomputes one variant's stock_quantity from its full ledger history
     * rather than trusting the materialised column, and rolls the change
     * up to the parent product. Intended for the nightly checksum job
     * (see App\Console\Commands\ReconcileInventory) as well as ad-hoc
     * repair.
     */
    public function reconcile(ProductVariant $variant): void
    {
        DB::transaction(function () use ($variant) {
            $trueStock = (int) $variant->inventoryLedger()->sum('delta');

            if ($trueStock !== $variant->stock_quantity) {
                $variant->update(['stock_quantity' => $trueStock]);
            }

            $this->recomputeProductStock($variant->product);
        });
    }

    private function recomputeProductStock(Product $product): void
    {
        $product->update([
            'stock_quantity' => $product->variants()->sum('stock_quantity'),
        ]);
    }
}

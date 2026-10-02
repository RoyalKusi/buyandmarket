<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §6.4 rule 2: inventory_ledger is the single source of truth for
 * stock; product_variants.stock_quantity is a materialised value,
 * recomputable from the ledger.
 */
class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjusting_stock_writes_a_ledger_row_and_updates_the_variant_and_product_rollup(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variantA = ProductVariant::factory()->for($product)->create(['stock_quantity' => 0]);
        $variantB = ProductVariant::factory()->for($product)->create(['stock_quantity' => 0]);

        $service = app(InventoryService::class);
        $service->adjustStock($variantA, 10, 'initial_stock');
        $service->adjustStock($variantB, 5, 'initial_stock');
        $service->adjustStock($variantA, -3, 'sale');

        $this->assertSame(7, $variantA->fresh()->stock_quantity);
        $this->assertSame(5, $variantB->fresh()->stock_quantity);
        $this->assertSame(12, $product->fresh()->stock_quantity);

        $this->assertDatabaseCount('inventory_ledger', 3);
        $this->assertDatabaseHas('inventory_ledger', ['variant_id' => $variantA->id, 'delta' => -3, 'reason_code' => 'sale']);
    }

    public function test_reconcile_repairs_drift_between_the_materialised_column_and_the_ledger(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 0]);

        $service = app(InventoryService::class);
        $service->adjustStock($variant, 20, 'initial_stock');

        // Simulate drift: something wrote to stock_quantity directly,
        // bypassing the ledger (exactly what the nightly job repairs).
        $variant->update(['stock_quantity' => 999]);

        $service->reconcile($variant);

        $this->assertSame(20, $variant->fresh()->stock_quantity);
        $this->assertSame(20, $product->fresh()->stock_quantity);
    }

    public function test_the_reconcile_command_repairs_every_variant(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 0]);

        app(InventoryService::class)->adjustStock($variant, 8, 'initial_stock');
        $variant->update(['stock_quantity' => 0]);

        $this->artisan('inventory:reconcile')->assertSuccessful();

        $this->assertSame(8, $variant->fresh()->stock_quantity);
    }
}

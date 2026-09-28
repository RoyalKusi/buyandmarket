<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TDD §6.2/§6.4 rule 2: append-only, single source of truth for
        // stock. product_variants.stock_quantity is a materialised value,
        // recomputable from this ledger by a nightly checksum/repair job
        // (see App\Console\Commands\ReconcileInventory).
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason_code');
            $table->foreignId('order_item_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};

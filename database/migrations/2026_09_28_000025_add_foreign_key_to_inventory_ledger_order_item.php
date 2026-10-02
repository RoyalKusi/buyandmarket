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
        // Run 1.2's inventory_ledger.order_item_id was left as a bare
        // nullable column since order_items didn't exist yet — this is
        // the expand-migrate-contract follow-up (TDD §12.2) now that it
        // does, for real referential integrity (TDD §10.9).
        Schema::table('inventory_ledger', function (Blueprint $table) {
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_ledger', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
        });
    }
};

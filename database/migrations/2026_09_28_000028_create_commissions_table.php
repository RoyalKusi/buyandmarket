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
        // TDD §7.4: "on succeeded, a commissions row is computed per
        // order_group ... commissions accumulate into a seller's pending
        // balance and are batched into a settlements row on the
        // platform's payout cycle." settlements/settlement_id linkage is
        // Run 1.5's own module 4 reference (seller-side) but the actual
        // settlements table and payout batching job are deferred — see
        // CHANGELOG.md for what that leaves unbuilt this run.
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_id')->constrained();
            $table->decimal('rate_applied', 5, 4);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->timestamps();

            $table->index('settlement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};

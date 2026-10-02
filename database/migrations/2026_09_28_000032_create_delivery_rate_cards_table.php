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
        // TDD §3.4 module 26: flat per-seller, per-zone, per-method
        // pricing (weight/distance-banded pricing is a later refinement,
        // not required for the Run 1.6 exit criterion). §6.2's own
        // canonical entity-reference table lists only this table for
        // modules 22+26 combined — this run folds module 22's
        // "shipping_methods" (which method a seller offers, with what
        // ETA) into these same rows rather than a separate table with an
        // identical (seller, zone, method) key, since the TDD's own DB
        // reference never lists shipping_methods as a distinct entity.
        Schema::create('delivery_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained();
            $table->foreignId('zone_id')->constrained('delivery_zones');
            $table->enum('method', ['standard', 'express', 'pickup']);
            $table->decimal('base_fee', 12, 2);
            $table->decimal('free_threshold', 12, 2)->nullable();
            $table->unsignedSmallInteger('eta_min_days');
            $table->unsignedSmallInteger('eta_max_days');
            $table->text('pickup_address')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['seller_id', 'zone_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_rate_cards');
    }
};

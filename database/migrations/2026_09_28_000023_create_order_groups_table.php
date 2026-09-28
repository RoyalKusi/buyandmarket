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
        // TDD §3.4 module 21: "a single checkout producing items from N
        // sellers creates one orders row (buyer's receipt) and N
        // order_groups rows (one per seller, each with its own
        // fulfilment status, shipment and commission calculation) — this
        // is the structural answer to marketplace order splitting."
        Schema::create('order_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained();
            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded',
            ])->default('pending');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('commission_amount', 12, 2)->default(0);
            // TDD §3.4 module 26 / Run 1.6: real per-zone delivery pricing
            // isn't wired in yet (delivery_rate_cards doesn't exist
            // until Fulfilment). This is a flat $0 placeholder, never
            // silently omitted from the schema it'll need.
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->timestamps();

            $table->index('order_id');
            $table->index(['seller_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_groups');
    }
};

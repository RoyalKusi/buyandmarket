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
        // TDD §6.4 rule 1: "order_items.price_at_purchase is always a
        // snapshot column, never a join to products.price — historical
        // orders must remain accurate after later price changes."
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->unsignedInteger('quantity');
            $table->decimal('price_at_purchase', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

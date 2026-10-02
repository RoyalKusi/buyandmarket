<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TDD §3.5-adjacent buyer feature (cart/wishlist named together in
     * Run 1.4's CHANGELOG: "cart and wishlist have no backing module
     * until Run 1.5/§3.5" — cart shipped in Run 1.5, wishlist deferred
     * until now). Deliberately the simplest possible shape: a toggle,
     * not a list-of-lists — the TDD names no multiple-wishlist feature.
     */
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};

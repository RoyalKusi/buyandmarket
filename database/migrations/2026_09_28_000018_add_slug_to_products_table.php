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
        // TDD §6.2's products entity reference doesn't list a slug column,
        // but the Storefront (this run) needs clean, stable product URLs —
        // an additive column, never a rewrite of Run 1.2's schema (TDD
        // §12.2: backward-compatible migrations only). Generated from the
        // title at creation (App\Services\ProductService), never entered
        // by the seller directly, so it stays URL-safe and unique.
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};

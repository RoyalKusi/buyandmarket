<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // TDD §6.4 rule 5: monetary columns are DECIMAL(12,2), never FLOAT.
            $table->decimal('base_price', 12, 2);
            $table->enum('status', ['draft', 'pending_review', 'published', 'archived'])->default('draft');
            // TDD §6.4 rule 2: inventory_ledger is the source of truth; this
            // is a cached/materialised value recomputable from the ledger.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->timestamps();
            // TDD §6.4 rule 3: soft-deleted, never hard-deleted — buyer-trust
            // continuity (a review shouldn't vanish because a product was
            // delisted) and audit-trail integrity.
            $table->softDeletes();

            $table->index(['store_id', 'status']);
            $table->index('category_id');
        });

        // TDD §6.2: FULLTEXT(title, description) — MySQL/MariaDB only.
        // SQLite (used in CI/local testing, see phpunit.xml) has no
        // equivalent ALTER TABLE ... FULLTEXT; the SearchProvider
        // abstraction (TDD §2.1, built out in Run 1.4) is what call sites
        // actually depend on, so skipping this index there changes no
        // application behaviour under test.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD FULLTEXT fulltext_title_description (title, description)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

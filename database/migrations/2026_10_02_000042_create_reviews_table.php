<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TDD module 33 (reviews), referenced since Run 1.3's CHANGELOG as a
     * prerequisite for the "Top Rated" seller badge and the PDP's
     * ratings row/reviews tab (flagged deferred since Run 1.4). One
     * review per verified purchase — `order_id` is the actual proof
     * (App\Services\ReviewService checks it belongs to the reviewer and
     * contains this product before allowing a review to be written), not
     * just a denormalised convenience.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            // Admin removal is a status change, not a row deletion — TDD
            // §6.4 rule 3's "soft-deleted, never hard-deleted" reasoning
            // applies here too: the review existed, the removal is itself
            // a fact worth keeping (who removed it, see audit_logs).
            $table->enum('status', ['published', 'removed'])->default('published');
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

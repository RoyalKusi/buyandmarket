<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TDD module 15 (sponsored placements), named alongside the
     * homepage's sponsored block and the AI-monitoring "ad/sponsored-
     * product recommendations" line, both flagged deferred since Run
     * 1.4/1.11 for lacking this module. Seller-submitted, admin-approved
     * — the same moderation shape as brand suggestions (module 11) —
     * never self-service-active, so a seller can't buy their way past
     * review.
     */
    public function up(): void
    {
        Schema::create('sponsored_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // TDD §6.4 rule 5: DECIMAL, never FLOAT, for money columns.
            // Declarative only — no billing/invoicing is wired to this
            // yet (flagged in CHANGELOG.md), same as every other
            // payment-adjacent gap this sandbox can't verify against a
            // live gateway.
            $table->decimal('daily_budget', 10, 2);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->enum('status', ['pending', 'active', 'rejected', 'ended'])->default('pending');
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_campaigns');
    }
};

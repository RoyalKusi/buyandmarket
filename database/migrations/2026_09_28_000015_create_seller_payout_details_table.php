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
        // TDD §3.1 module 4: the onboarding stepper's "bank/payout details"
        // step. Full settlement/payout processing is Run 1.5's scope
        // (TDD §7.4) — this table exists so the onboarding step has
        // somewhere to persist to. account_number is encrypted at rest
        // (TDD §8: buyer/seller financial data is sensitive by default,
        // not just payment-method tokens).
        Schema::create('seller_payout_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->text('account_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_payout_details');
    }
};

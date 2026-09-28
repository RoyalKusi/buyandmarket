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
        // TDD §3.1 module 4: "Stepper: business info -> KYC documents ->
        // bank/payout details -> store setup -> first product -> admin
        // review; resumable, each step's completion timestamped."
        Schema::create('seller_onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->enum('step', [
                'business_info', 'kyc_documents', 'bank_details', 'store_setup', 'first_product', 'admin_review',
            ]);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_onboarding_steps');
    }
};

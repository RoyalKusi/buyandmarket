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
        // TDD §3.1 module 2 / §6.2: the bare seller record a product's
        // store_id ultimately hangs off. Only the minimal columns the
        // catalogue (Run 1.2) needs to exist ship here — the onboarding
        // stepper, KYC documents/review and badge computation (module
        // 4-6) are Run 1.3's own migrations, built on top of this table.
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->enum('status', [
                'pending', 'under_review', 'approved', 'active', 'suspended', 'terminated',
            ])->default('pending');
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};

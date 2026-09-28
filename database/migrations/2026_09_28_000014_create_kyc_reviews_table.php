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
        // TDD §3.1 module 5: "reviewed by admin/sub-admin with an
        // audit-logged decision." One row per review decision on a
        // seller's overall KYC submission — a resubmission after rejection
        // produces a new row, preserving full review history.
        Schema::create('kyc_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->enum('decision', ['approved', 'rejected']);
            $table->string('reason_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['seller_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_reviews');
    }
};

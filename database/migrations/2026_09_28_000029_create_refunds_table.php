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
        // TDD §7.4: "Refunds/partial refunds: modeled as their own
        // refunds row against a payments row, supporting partial
        // amounts; a refund against a multi-seller order only reverses
        // the affected order_group's commission, not the whole order's."
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('order_group_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

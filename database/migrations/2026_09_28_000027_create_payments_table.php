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
        // TDD §7.4: initiated -> processing -> succeeded|failed;
        // succeeded -> refund_requested -> refunded|partially_refunded.
        // UNIQUE(provider, provider_reference) is the idempotency key a
        // redelivered webhook is checked against (§7.4).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            // 'pesepay' or 'paynow' — App\Contracts\PaymentGateway's two
            // current implementations.
            $table->string('provider');
            $table->string('provider_reference');
            $table->decimal('amount', 12, 2);
            $table->enum('status', [
                'initiated', 'processing', 'succeeded', 'failed',
                'refund_requested', 'refunded', 'partially_refunded',
            ])->default('initiated');
            // Raw provider webhook payload, archived for dispute
            // resolution (TDD §7.4).
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

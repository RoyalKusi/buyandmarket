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
        // TDD §3.4 module 18 / §5.9 checkout state machine: CartReview ->
        // AddressSelection -> DeliveryMethod -> PaymentProcessing ->
        // OrderConfirmed (or -> PaymentFailed, retryable). Ephemeral,
        // expires after 30 minutes of inactivity; guest and authenticated
        // paths converge on the same table (§5.9: "guest checkout is
        // fully supported").
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->enum('status', [
                'cart_review', 'address_selection', 'delivery_method', 'payment_processing', 'payment_failed', 'order_confirmed',
            ])->default('cart_review');
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            // Per-seller-group delivery method selection: {store_id: {method, fee}}.
            $table->json('delivery_selection')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};

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
        // TDD §3.4 module 22/23, §6.3: one shipment per order_group.
        // shipper_id is nullable — null means "platform-dispatched,
        // unassigned," open for any active shipper to claim
        // (App\Services\ShippingService::claim()); a seller-selected
        // courier is assigned directly at creation instead.
        Schema::create('order_group_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipper_id')->nullable()->constrained('shippers')->nullOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones');
            $table->enum('method', ['standard', 'express', 'pickup']);
            $table->enum('status', [
                'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'rescheduled',
            ])->default('assigned');
            $table->string('proof_photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique('order_group_id');
            $table->index('shipper_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_group_shipments');
    }
};

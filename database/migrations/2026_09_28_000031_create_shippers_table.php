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
        // TDD §3.4 module 23: shippers are a distinct role (§8.1),
        // mirroring the sellers table's shape — one shipper profile per
        // user. Assignment either seller-selected (own courier) or
        // platform-dispatched (pooled marketplace): both are represented
        // the same way, an order_group_shipments.shipper_id that's either
        // set directly by the seller or claimed by a shipper from the
        // unassigned pool (App\Services\ShippingService).
        Schema::create('shippers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->timestamps();

            $table->unique('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shippers');
    }
};

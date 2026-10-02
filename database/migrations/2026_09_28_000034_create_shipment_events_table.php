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
        // TDD §3.4 module 24: "every status transition ... is an
        // immutable event with actor, timestamp, optional geopoint — the
        // buyer-facing tracking timeline is a read projection of this
        // log." Append-only: no UPDATE/DELETE grants at the DB-user
        // level, matching inventory_ledger and audit_logs (§6.4 rule 2).
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_shipment_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'rescheduled',
            ]);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_group_shipment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};

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
        // TDD §3.4 module 25: Zimbabwe's administrative hierarchy
        // (province -> city -> area), seeded at launch
        // (database/seeders/DeliveryZoneSeeder.php). A seller opts into
        // specific zones by having a delivery_rate_cards row for them —
        // there is deliberately no separate "nationwide" flag.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->enum('level', ['province', 'city', 'area']);
            $table->string('name');
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};

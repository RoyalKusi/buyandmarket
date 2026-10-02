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
        // TDD §3.1 module 1 lists buyer_profiles alongside users but never
        // details its columns; §6.6 checkout needs "saved addresses as
        // selectable cards" now, so that's what ships — a full buyer
        // profile (preferences, etc.) stays deferred until a run actually
        // needs one (flagged since Run 1.3's CHANGELOG).
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone');
            // TDD §3.4 module 25: Zimbabwe's province -> city -> area
            // administrative hierarchy.
            $table->string('province');
            $table->string('city');
            $table->string('area')->nullable();
            $table->string('street_address');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

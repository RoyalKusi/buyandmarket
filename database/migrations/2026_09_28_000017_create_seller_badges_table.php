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
        // TDD §3.1 module 6: "Badge rules are computable, not manually
        // assigned ... recalculated nightly by a scheduled job." A row's
        // presence IS the badge being currently held; the job deletes rows
        // for badges a seller no longer qualifies for rather than
        // soft-flagging them.
        Schema::create('seller_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->enum('badge', ['verified', 'top_rated', 'new_seller', 'fast_responder']);
            $table->timestamp('awarded_at')->useCurrent();

            $table->unique(['seller_id', 'badge']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_badges');
    }
};

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
        // TDD §5.9: "guest checkout is fully supported." Run 1.5's
        // checkout backend never actually needed to persist a guest's
        // address (its own test drives the API with a pre-existing
        // authenticated-user address), so this NOT NULL constraint went
        // unnoticed until the storefront checkout UI (Run 1.12) tried to
        // save one for a guest. Addresses with a null user_id belong to
        // no account — never returned by `$user->addresses()`, only
        // reachable via the checkout_sessions.address_id that created
        // them.
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

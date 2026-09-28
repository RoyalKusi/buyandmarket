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
        // Permission grants for the fixed platform roles (buyer/seller/shipper/
        // admin). Sub-admin grants live in admin_role_permissions instead,
        // scoped per named admin_roles row rather than the enum itself.
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['buyer', 'seller', 'shipper', 'admin']);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};

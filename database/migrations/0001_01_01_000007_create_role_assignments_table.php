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
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['buyer', 'seller', 'shipper', 'admin', 'sub_admin']);
            // For role = sub_admin, scope points at an admin_roles.id (least-privilege
            // permission set, TDD §3.6 module 31); null for every other role.
            $table->foreignId('scope')->nullable()->constrained('admin_roles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};

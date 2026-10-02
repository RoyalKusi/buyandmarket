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
        // TDD §3.1 module 5: document types are national ID, proof of
        // address, business registration (optional for individual
        // sellers). file_path is a Storage::disk('kyc') relative path,
        // never a public URL — access is only ever through a signed,
        // time-limited URL (§8.3).
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['national_id', 'proof_of_address', 'business_registration']);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->index(['seller_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};

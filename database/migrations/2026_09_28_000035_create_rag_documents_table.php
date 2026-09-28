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
        // TDD §6.2: one row per source entity (product, store — the
        // source types this run's chunking strategy covers; policy/FAQ
        // and promotion ingestion are flagged deferred in CHANGELOG.md,
        // since their own modules (44, 14) don't exist yet). Re-embedding
        // is triggered by a content_hash change (App\Services\Ai\
        // IngestionService), not blindly on every save.
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('content_hash', 64);
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};

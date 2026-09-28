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
        // TDD §2.1/§13 stage 6: the vector column is a plain JSON array of
        // floats here, ranked by an in-application cosine similarity pass
        // (App\Services\Ai\RetrievalService) rather than a native vector
        // index — this launch topology has no dedicated vector store, the
        // same "boring now, swappable later" shape as SearchProvider's
        // MySQL-full-text-now/Meilisearch-later split. See docs/adr/0006.
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('rag_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->json('vector');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};

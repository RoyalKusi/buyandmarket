<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TDD §3.2 module 7 / §5.8: the product-image pipeline, deferred
     * since Run 1.2 ("adding a bare, un-pipelined image field now would
     * mean either reworking it later or shipping catalogue images that
     * never got a quality/authenticity pass"). This is that pipeline:
     * every upload is validated, quality-scored, and resized into a
     * fixed set of variants before it is usable on the storefront — see
     * App\Services\ProductImageService.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size_bytes');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // TDD §5.8 "quality scoring": deterministic resolution/
            // aspect-ratio checks, not an ML model (no vision-capable
            // provider is configured — see docs/adr/0007). 'rejected'
            // images are kept for the seller to see why, not deleted.
            $table->enum('status', ['processed', 'rejected'])->default('processed');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

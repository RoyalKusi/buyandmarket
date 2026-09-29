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
        // TDD §5.6/§7.7: an AI-generated description suggestion sits
        // beside the product's real (seller-authored or already-accepted)
        // description until the seller explicitly Accepts or Discards it
        // — never auto-applied (§3.2 rule 7's own "AI-assisted fields are
        // suggestions written to the draft, never auto-published"
        // principle, applied here to an existing published/draft field
        // rather than a separate product_drafts table).
        Schema::table('products', function (Blueprint $table) {
            $table->text('ai_suggested_description')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ai_suggested_description');
        });
    }
};

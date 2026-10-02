<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TDD module 41 (analytics event stream), named as the prerequisite
     * for seller sales summaries/performance insights and the storefront's
     * views/conversion signals — flagged deferred since Run 1.7/1.11 for
     * not existing at all. Append-only, same shape discipline as
     * audit_logs, but this is a behavioural signal (what buyers did), not
     * a privileged-mutation record (who changed what) — the two are kept
     * as separate tables rather than overloading audit_logs' meaning.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->enum('event_type', ['product_view', 'add_to_cart', 'order_placed']);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};

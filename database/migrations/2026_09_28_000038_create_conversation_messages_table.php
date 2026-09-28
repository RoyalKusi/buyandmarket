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
        // TDD §5.4: an assistant message can propose a state-changing
        // tool call (tool_calls, requires_confirmation = true) without
        // executing it; App\Services\Ai\AssistantService::confirmAction()
        // is the only path that turns a proposal into a tool-role
        // message + executed effect. citations records which retrieved
        // chunks/tool results actually grounded this message (TDD §5.3
        // rule 3), independent of whatever the model's own prose says.
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->text('content')->nullable();
            $table->string('tool_name')->nullable();
            $table->json('tool_calls')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->json('citations')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};

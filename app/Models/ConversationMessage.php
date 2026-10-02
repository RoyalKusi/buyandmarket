<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array<string, mixed>>|null $tool_calls
 * @property array<int, array<string, mixed>>|null $citations
 */
class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_name',
        'tool_calls',
        'requires_confirmation',
        'confirmed',
        'citations',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'citations' => 'array',
            'requires_confirmation' => 'boolean',
            'confirmed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

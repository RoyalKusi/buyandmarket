<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'vector',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'vector' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RagDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RagDocument::class, 'document_id');
    }
}

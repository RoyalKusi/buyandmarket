<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RagDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'source_id',
        'content_hash',
    ];

    /**
     * @return HasMany<Embedding, $this>
     */
    public function embeddings(): HasMany
    {
        return $this->hasMany(Embedding::class, 'document_id');
    }

    /**
     * Not a true polymorphic relation (source_type stores a short tag
     * like "product", not a class name) — resolved explicitly so the tag
     * stays a stable, storage-level identifier independent of the App\
     * Models namespace.
     */
    public function source(): ?Model
    {
        $class = match ($this->source_type) {
            'product' => Product::class,
            'store' => Store::class,
            default => null,
        };

        return $class ? $class::find($this->source_id) : null;
    }
}

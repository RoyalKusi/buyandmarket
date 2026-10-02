<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'disk_path',
        'original_filename',
        'mime_type',
        'width',
        'height',
        'size_bytes',
        'alt_text',
        'is_primary',
        'sort_order',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }

    /**
     * App\Services\ProductImageService writes the thumb/large variants
     * next to the original using this same suffix convention, so no
     * extra columns are needed to find them.
     */
    public function variantUrl(string $variant): string
    {
        return Storage::disk('public')->url($this->variantPath($variant));
    }

    public function variantPath(string $variant): string
    {
        $extension = pathinfo($this->disk_path, PATHINFO_EXTENSION);
        $withoutExtension = Str::beforeLast($this->disk_path, ".{$extension}");

        return "{$withoutExtension}_{$variant}.{$extension}";
    }
}

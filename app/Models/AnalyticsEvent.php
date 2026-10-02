<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * $timestamps is false (no updated_at column exists) so Larastan's
 * automatic Eloquent timestamp-column typing doesn't apply here even
 * though created_at is still a real, manually-managed cast column.
 *
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'subject_type',
        'subject_id',
        'user_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

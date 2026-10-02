<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'level',
        'name',
    ];

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'parent_id');
    }

    /**
     * @return HasMany<DeliveryZone, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DeliveryZone::class, 'parent_id');
    }
}

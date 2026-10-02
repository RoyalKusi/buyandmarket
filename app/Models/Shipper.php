<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipper extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'status',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderGroupShipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(OrderGroupShipment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

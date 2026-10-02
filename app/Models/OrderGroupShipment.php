<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderGroupShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_group_id',
        'shipper_id',
        'zone_id',
        'method',
        'status',
        'proof_photo_path',
        'signature_path',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderGroup, $this>
     */
    public function orderGroup(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class);
    }

    /**
     * @return BelongsTo<Shipper, $this>
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    /**
     * @return HasMany<ShipmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('created_at');
    }

    public function isUnclaimed(): bool
    {
        return $this->shipper_id === null;
    }
}

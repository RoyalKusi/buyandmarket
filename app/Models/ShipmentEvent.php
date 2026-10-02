<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_group_shipment_id',
        'event_type',
        'actor_id',
        'lat',
        'lng',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<OrderGroupShipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderGroupShipment::class, 'order_group_shipment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

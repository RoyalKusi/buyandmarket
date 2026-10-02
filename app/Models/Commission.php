<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_group_id',
        'rate_applied',
        'amount',
        'settlement_id',
    ];

    protected function casts(): array
    {
        return [
            'rate_applied' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OrderGroup, $this>
     */
    public function orderGroup(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class);
    }
}

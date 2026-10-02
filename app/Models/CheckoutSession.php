<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TDD §5.9 checkout state machine. Only App\Services\CheckoutService
 * transitions this model's status.
 *
 * @property array<int|string, array{fee?: string|int|float}>|null $delivery_selection
 * @property Carbon $expires_at
 */
class CheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'user_id',
        'guest_email',
        'guest_phone',
        'status',
        'address_id',
        'delivery_selection',
        'order_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_selection' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

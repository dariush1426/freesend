<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'plan_id',
    'user_subscription_id',
    'order_number',
    'gateway',
    'amount',
    'currency',
    'status',
    'description',
    'paid_at',
    'failed_at',
    'notes',
])]
class SubscriptionOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REDIRECTED = 'redirected';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (static::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'order_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->payments()->latestOfMany();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'gateway',
    'status',
    'amount',
    'track_id',
    'gateway_result',
    'gateway_status',
    'callback_success',
    'request_payload',
    'request_response',
    'callback_payload',
    'verify_response',
    'paid_at',
    'failed_at',
    'message',
])]
class SubscriptionPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REDIRECTED = 'redirected';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'callback_success' => 'boolean',
            'request_payload' => 'array',
            'request_response' => 'array',
            'callback_payload' => 'array',
            'verify_response' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class, 'order_id');
    }
}

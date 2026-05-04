<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mobile',
    'purpose',
    'code_hash',
    'attempts',
    'expires_at',
    'consumed_at',
    'meta',
])]
class SmsOtp extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}

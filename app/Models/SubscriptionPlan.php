<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'is_active',
    'is_default',
    'sort_order',
    'price_amount',
    'duration_value',
    'duration_unit',
    'max_upload_size_mb',
    'max_storage_mb',
    'expire_options',
    'allow_public_links',
    'allow_password_protection',
    'allow_custom_expiry',
    'allow_never_expire',
    'allow_personal_storage',
    'allow_team_features',
    'max_team_members',
    'allow_signature_workflow',
    'allow_folders',
    'allow_ai_features',
])]
class SubscriptionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'price_amount' => 'integer',
            'duration_value' => 'integer',
            'expire_options' => 'array',
            'allow_public_links' => 'boolean',
            'allow_password_protection' => 'boolean',
            'allow_custom_expiry' => 'boolean',
            'allow_never_expire' => 'boolean',
            'allow_personal_storage' => 'boolean',
            'allow_team_features' => 'boolean',
            'allow_signature_workflow' => 'boolean',
            'allow_folders' => 'boolean',
            'allow_ai_features' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class, 'plan_id');
    }

    public function hasDuration(): bool
    {
        return (int) $this->duration_value > 0
            && in_array((string) $this->duration_unit, ['day', 'month'], true);
    }

    public function isPaid(): bool
    {
        return (int) $this->price_amount > 0;
    }
}

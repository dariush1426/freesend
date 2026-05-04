<?php

namespace App\Support;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class PlanPolicy
{
    public const EXPIRE_OPTION_VALUES = ['default', '1', '2', '5', '12', '24', 'custom', 'never'];

    public static function profileForUser(?User $user): array
    {
        $subscription = self::activeSubscriptionForUser($user);
        $plan = $subscription?->plan ?: self::defaultPlan();
        $expireOptions = self::expireOptionValues($plan);

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'expire_options' => $expireOptions,
            'price_amount' => $plan?->price_amount,
            'duration_value' => $plan?->duration_value,
            'duration_unit' => $plan?->duration_unit,
            'allow_public_links' => (bool) ($plan?->allow_public_links ?? true),
            'allow_password_protection' => (bool) ($plan?->allow_password_protection ?? true),
            'allow_custom_expiry' => in_array('custom', $expireOptions, true),
            'allow_never_expire' => in_array('never', $expireOptions, true),
            'max_upload_size_mb' => $plan?->max_upload_size_mb,
            'max_storage_mb' => $plan?->max_storage_mb,
            'allow_team_features' => (bool) ($plan?->allow_team_features ?? false),
            'max_team_members' => $plan?->max_team_members,
            'allow_personal_storage' => (bool) ($plan?->allow_personal_storage ?? false),
            'allow_signature_workflow' => (bool) ($plan?->allow_signature_workflow ?? false),
            'allow_folders' => (bool) ($plan?->allow_folders ?? false),
            'allow_ai_features' => (bool) ($plan?->allow_ai_features ?? false),
        ];
    }

    public static function effectiveMaxUploadSizeMb(?User $user, int $systemMaxMb): int
    {
        $planMax = self::profileForUser($user)['max_upload_size_mb'];

        if (! is_numeric($planMax) || (int) $planMax <= 0) {
            return $systemMaxMb;
        }

        return max(1, min($systemMaxMb, (int) $planMax));
    }

    public static function expireOptionValues(SubscriptionPlan|array|null $plan = null): array
    {
        $rawOptions = $plan instanceof SubscriptionPlan
            ? $plan->expire_options
            : ($plan['expire_options'] ?? null);

        $options = collect(is_array($rawOptions) ? $rawOptions : ['default', '1', '2', '5', '12', '24', 'custom'])
            ->map(fn (mixed $value) => trim((string) $value))
            ->filter(fn (string $value) => in_array($value, self::EXPIRE_OPTION_VALUES, true))
            ->unique()
            ->values();

        $allowCustomExpiry = $plan instanceof SubscriptionPlan
            ? $plan->allow_custom_expiry
            : (bool) ($plan['allow_custom_expiry'] ?? true);

        $allowNeverExpire = $plan instanceof SubscriptionPlan
            ? $plan->allow_never_expire
            : (bool) ($plan['allow_never_expire'] ?? false);

        if (! $allowCustomExpiry) {
            $options = $options->reject(fn (string $value) => $value === 'custom')->values();
        } elseif (! $options->contains('custom')) {
            $options->push('custom');
        }

        if ($allowNeverExpire) {
            if (! $options->contains('never')) {
                $options->push('never');
            }
        } else {
            $options = $options->reject(fn (string $value) => $value === 'never')->values();
        }

        if (! $options->contains('default')) {
            $options->prepend('default');
        }

        return $options->values()->all();
    }

    public static function assignPlan(
        User $user,
        SubscriptionPlan $plan,
        ?Carbon $endsAt = null,
        ?User $assignedBy = null,
        ?string $notes = null,
    ): UserSubscription {
        $currentSubscriptions = self::activeSubscriptionsQuery($user)->get();

        foreach ($currentSubscriptions as $current) {
            $current->forceFill([
                'status' => UserSubscription::STATUS_EXPIRED,
                'ends_at' => $current->ends_at && $current->ends_at->isPast() ? $current->ends_at : now(),
            ])->save();
        }

        return UserSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'assigned_by' => $assignedBy?->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => $endsAt ?: self::resolveSubscriptionEndDate($plan),
            'notes' => $notes,
        ]);
    }

    public static function activeSubscriptionForUser(?User $user): ?UserSubscription
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('subscriptions')) {
            /** @var Collection<int, UserSubscription> $subscriptions */
            $subscriptions = $user->subscriptions;

            return $subscriptions
                ->filter(fn (UserSubscription $subscription) => $subscription->isCurrentlyActive())
                ->sortByDesc(fn (UserSubscription $subscription) => $subscription->starts_at?->getTimestamp() ?? 0)
                ->first();
        }

        return self::activeSubscriptionsQuery($user)
            ->with('plan')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function defaultPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public static function resolveSubscriptionEndDate(SubscriptionPlan $plan, ?Carbon $startAt = null): ?Carbon
    {
        if (! $plan->hasDuration()) {
            return null;
        }

        $startAt ??= now();

        return $plan->duration_unit === 'month'
            ? $startAt->copy()->addMonths($plan->duration_value)
            : $startAt->copy()->addDays($plan->duration_value);
    }

    public static function formatPlanDuration(?SubscriptionPlan $plan, string $prefix = 'ui.subscriptions'): string
    {
        if (! $plan || ! $plan->hasDuration()) {
            return __($prefix.'.duration_unlimited');
        }

        $value = (int) $plan->duration_value;
        $unit = (string) $plan->duration_unit;

        return $unit === 'month'
            ? __($prefix.'.duration_months', ['count' => Number::format($value)])
            : __($prefix.'.duration_days', ['count' => Number::format($value)]);
    }

    public static function formatPlanPrice(?SubscriptionPlan $plan, string $prefix = 'ui.subscriptions'): string
    {
        if (! $plan || ! $plan->isPaid()) {
            return __($prefix.'.free_price');
        }

        return __($prefix.'.price_rial', ['amount' => Number::format((int) $plan->price_amount)]);
    }

    private static function activeSubscriptionsQuery(User $user)
    {
        return $user->subscriptions()
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}

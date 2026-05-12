<?php

namespace App\Support;

use App\Models\SharedFile;
use App\Models\User;

class PersonalStorageQuota
{
    public const NEAR_CAPACITY_THRESHOLD_PERCENT = 85;

    public static function profileForUser(User $user): array
    {
        $planProfile = PlanPolicy::profileForUser($user);
        $quotaMb = is_numeric($planProfile['max_storage_mb'] ?? null)
            ? max(0, (int) $planProfile['max_storage_mb'])
            : null;
        $quotaBytes = $quotaMb && $quotaMb > 0 ? $quotaMb * 1024 * 1024 : null;
        $usedBytes = self::usedBytes($user);
        $filesCount = self::filesCount($user);
        $remainingBytes = $quotaBytes === null
            ? null
            : max(0, $quotaBytes - $usedBytes);
        $usedPercent = $quotaBytes === null || $quotaBytes === 0
            ? null
            : min(100, (int) round(($usedBytes / $quotaBytes) * 100));

        return [
            'enabled' => (bool) ($planProfile['allow_personal_storage'] ?? false),
            'quota_mb' => $quotaMb,
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $usedBytes,
            'remaining_bytes' => $remainingBytes,
            'used_percent' => $usedPercent,
            'is_near_capacity' => $quotaBytes !== null && $remainingBytes !== null
                ? ($remainingBytes > 0 && $usedPercent !== null && $usedPercent >= self::NEAR_CAPACITY_THRESHOLD_PERCENT)
                : false,
            'is_full' => $quotaBytes !== null && $remainingBytes !== null ? $remainingBytes < 1 : false,
            'has_unlimited_quota' => $quotaBytes === null,
            'files_count' => $filesCount,
        ];
    }

    public static function canStoreUpload(User $user, int $incomingSize): bool
    {
        $profile = self::profileForUser($user);

        if (! $profile['enabled'] || $incomingSize < 1) {
            return false;
        }

        if ($profile['quota_bytes'] === null) {
            return true;
        }

        return ($profile['used_bytes'] + $incomingSize) <= $profile['quota_bytes'];
    }

    public static function disableNoExpiryReceivingIfUnavailable(User $user, ?array $profile = null): bool
    {
        if (! (bool) $user->allow_receive_no_expiry) {
            return false;
        }

        $profile ??= self::profileForUser($user);

        if (($profile['enabled'] ?? false) && ! ($profile['is_full'] ?? false)) {
            return false;
        }

        $user->forceFill([
            'allow_receive_no_expiry' => false,
        ])->save();

        return true;
    }

    public static function isNearCapacity(User $user, int $thresholdPercent = self::NEAR_CAPACITY_THRESHOLD_PERCENT): bool
    {
        $profile = self::profileForUser($user);

        if (! $profile['enabled'] || $profile['quota_bytes'] === null) {
            return false;
        }

        if (($profile['remaining_bytes'] ?? 0) < 1) {
            return false;
        }

        return (int) ($profile['used_percent'] ?? 0) >= $thresholdPercent;
    }

    public static function usedBytes(User $user): int
    {
        return (int) SharedFile::query()
            ->where('owner_id', $user->id)
            ->where('is_personal_storage', true)
            ->where('status', '!=', SharedFile::STATUS_DELETED)
            ->sum('size');
    }

    public static function filesCount(User $user): int
    {
        return (int) SharedFile::query()
            ->where('owner_id', $user->id)
            ->where('is_personal_storage', true)
            ->where('status', '!=', SharedFile::STATUS_DELETED)
            ->count();
    }
}

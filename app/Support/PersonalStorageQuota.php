<?php

namespace App\Support;

use App\Models\SharedFile;
use App\Models\User;

class PersonalStorageQuota
{
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

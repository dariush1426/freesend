<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if ($query === '') {
            return response()->json([
                'found' => false,
                'users' => [],
            ]);
        }

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('mobile', 'like', "%{$query}%");
            })
            ->orderBy('username')
            ->limit(8)
            ->get(['id', 'username', 'full_name', 'email', 'mobile', 'allow_receive_no_expiry']);

        return response()->json([
            'found' => $users->isNotEmpty(),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'capabilities' => $this->capabilitiesForUser($user),
            ])->all(),
        ]);
    }

    private function capabilitiesForUser(User $user): array
    {
        $planProfile = PlanPolicy::profileForUser($user);
        $storageProfile = PersonalStorageQuota::profileForUser($user);

        if (PersonalStorageQuota::disableNoExpiryReceivingIfUnavailable($user, $storageProfile)) {
            $user->refresh();
            $storageProfile = PersonalStorageQuota::profileForUser($user);
        }

        $storageEnabled = (bool) ($planProfile['allow_personal_storage'] ?? false);
        $storageFull = $storageEnabled
            && $storageProfile['quota_bytes'] !== null
            && (int) ($storageProfile['remaining_bytes'] ?? 0) < 1;
        $storageNearCapacity = $storageEnabled
            && ! $storageFull
            && PersonalStorageQuota::isNearCapacity($user);

        return [
            'allow_personal_storage' => $storageEnabled,
            'allow_never_expire' => $storageEnabled && ! $storageFull && (bool) $user->allow_receive_no_expiry,
            'allow_note_without_file' => $storageEnabled && ! $storageFull,
            'storage_near_capacity' => $storageNearCapacity,
            'storage_full' => $storageFull,
            'storage_used_percent' => $storageProfile['used_percent'],
            'receiver_prefers_no_expiry' => (bool) $user->allow_receive_no_expiry,
        ];
    }
}

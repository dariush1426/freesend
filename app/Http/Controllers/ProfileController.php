<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MobileNumber;
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'planProfile' => PlanPolicy::profileForUser($user),
            'storageProfile' => PersonalStorageQuota::profileForUser($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:32', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:20'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'allow_receive_no_expiry' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedMobile = null;

        if (! empty($validated['mobile'])) {
            $normalizedMobile = MobileNumber::normalize($validated['mobile']);

            if (! $normalizedMobile) {
                throw ValidationException::withMessages([
                    'mobile' => __('ui.profile.mobile_invalid'),
                ]);
            }

            $mobileExists = User::query()
                ->where('mobile', $normalizedMobile)
                ->whereKeyNot($user->id)
                ->exists();

            if ($mobileExists) {
                throw ValidationException::withMessages([
                    'mobile' => __('ui.profile.mobile_taken'),
                ]);
            }
        }

        $emailChanged = $user->email !== $validated['email'];
        $mobileChanged = (string) $user->mobile !== (string) $normalizedMobile;

        $user->username = $validated['username'];
        $user->name = $validated['username'];
        $user->email = $validated['email'];
        $user->mobile = $normalizedMobile;
        $user->full_name = $validated['full_name'] ?? null;
        $user->allow_receive_no_expiry = ($user->allow_receive_no_expiry ?? false);

        if (PlanPolicy::profileForUser($user)['allow_personal_storage'] ?? false) {
            $user->allow_receive_no_expiry = $request->boolean('allow_receive_no_expiry');
        } else {
            $user->allow_receive_no_expiry = false;
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($mobileChanged) {
            $user->mobile_verified_at = null;
        }

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return back()->with('status', __('ui.profile.saved'));
    }
}

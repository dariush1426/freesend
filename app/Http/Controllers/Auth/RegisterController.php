<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        $prefillContact = trim((string) $request->query('contact', ''));

        return view('auth.register', [
            'prefill' => [
                'full_name' => trim((string) $request->query('name', '')),
                'email' => filter_var($prefillContact, FILTER_VALIDATE_EMAIL) ? $prefillContact : '',
                'mobile' => filter_var($prefillContact, FILTER_VALIDATE_EMAIL) ? '' : $prefillContact,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:32', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedMobile = null;

        if (! empty($validated['mobile'])) {
            $normalizedMobile = MobileNumber::normalize($validated['mobile']);

            if (! $normalizedMobile) {
                throw ValidationException::withMessages([
                    'mobile' => __('messages.auth.mobile_invalid'),
                ]);
            }

            if (User::query()->where('mobile', $normalizedMobile)->exists()) {
                throw ValidationException::withMessages([
                    'mobile' => __('messages.auth.mobile_taken'),
                ]);
            }
        }

        $user = User::create([
            'name' => $validated['username'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'mobile' => $normalizedMobile,
            'full_name' => $validated['full_name'] ?? null,
            'is_admin' => User::query()->count() === 0,
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('status', __('messages.auth.account_created'));
    }
}

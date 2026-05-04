<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobileNumber;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OtpAuthController extends Controller
{
    private const REGISTER_MOBILE_SESSION_KEY = 'otp_register_mobile';

    private const REGISTER_VERIFIED_SESSION_KEY = 'otp_register_verified_mobile';

    public function showLogin(Request $request): View
    {
        if ($request->boolean('reset')) {
            $request->session()->forget('otp_login_mobile');
        }

        return view('auth.otp-login', [
            'otpStep' => session()->has('otp_login_mobile'),
            'otpMobile' => (string) session('otp_login_mobile', ''),
        ]);
    }

    public function sendLoginOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        $mobile = MobileNumber::normalize($validated['mobile']);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_invalid'),
            ]);
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_not_found'),
            ]);
        }

        try {
            $ttl = OtpService::send($mobile, 'login', [
                'user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'mobile' => $exception->getMessage(),
            ]);
        }

        session()->put('otp_login_mobile', $mobile);

        return redirect()
            ->route('otp.login')
            ->with('status', __('messages.auth.otp_sent', ['minutes' => $ttl]));
    }

    public function verifyLoginOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'otp_code' => ['required', 'string', 'min:4', 'max:8'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $mobile = MobileNumber::normalize($validated['mobile']);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_invalid'),
            ]);
        }

        $expectedMobile = (string) session('otp_login_mobile', '');

        if ($expectedMobile === '' || $expectedMobile !== $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.otp_send_first'),
            ]);
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_not_found'),
            ]);
        }

        if (! OtpService::verify($mobile, 'login', trim($validated['otp_code']))) {
            throw ValidationException::withMessages([
                'otp_code' => __('messages.auth.otp_invalid'),
            ]);
        }

        if (! $user->mobile_verified_at) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('otp_login_mobile');

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request): View
    {
        if ($request->boolean('reset')) {
            $this->forgetRegisterSession($request);
        }

        $verifiedMobile = (string) $request->session()->get(self::REGISTER_VERIFIED_SESSION_KEY, '');
        $otpMobile = $verifiedMobile !== ''
            ? $verifiedMobile
            : (string) $request->session()->get(self::REGISTER_MOBILE_SESSION_KEY, '');

        return view('auth.otp-register', [
            'otpStep' => $otpMobile !== '' && $verifiedMobile === '',
            'profileStep' => $verifiedMobile !== '',
            'otpMobile' => $otpMobile,
        ]);
    }

    public function sendRegisterOtp(Request $request): RedirectResponse
    {
        $validated = $this->validateRegisterMobilePayload($request);
        $mobile = MobileNumber::normalize($validated['mobile']);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_invalid'),
            ]);
        }

        if (User::query()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_taken'),
            ]);
        }

        try {
            $ttl = OtpService::send($mobile, 'register');
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'mobile' => $exception->getMessage(),
            ]);
        }

        $request->session()->put(self::REGISTER_MOBILE_SESSION_KEY, $mobile);
        $request->session()->forget(self::REGISTER_VERIFIED_SESSION_KEY);

        return redirect()
            ->route('otp.register')
            ->with('status', __('messages.auth.otp_sent', ['minutes' => $ttl]));
    }

    public function verifyRegisterOtp(Request $request): RedirectResponse
    {
        $validated = $this->validateRegisterMobilePayload($request);
        $validatedOtp = $request->validate([
            'otp_code' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $mobile = MobileNumber::normalize($validated['mobile']);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_invalid'),
            ]);
        }

        $expectedMobile = (string) $request->session()->get(self::REGISTER_MOBILE_SESSION_KEY, '');

        if ($expectedMobile === '' || $expectedMobile !== $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.otp_send_first'),
            ]);
        }

        if (! OtpService::verify($mobile, 'register', trim($validatedOtp['otp_code']))) {
            throw ValidationException::withMessages([
                'otp_code' => __('messages.auth.otp_invalid'),
            ]);
        }

        if (User::query()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.mobile_taken'),
            ]);
        }

        $request->session()->put(self::REGISTER_VERIFIED_SESSION_KEY, $mobile);

        return redirect()
            ->route('otp.register')
            ->with('status', __('messages.auth.otp_verified_continue_register'));
    }

    public function completeRegister(Request $request): RedirectResponse
    {
        $validated = $this->validateRegisterDetailsPayload($request);
        $mobile = (string) $request->session()->get(self::REGISTER_VERIFIED_SESSION_KEY, '');

        if ($mobile === '') {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.complete_registration_after_otp'),
            ]);
        }

        $normalizedMobile = MobileNumber::normalize($mobile);

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

        try {
            $user = User::create([
                'name' => $validated['username'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'mobile' => $normalizedMobile,
                'full_name' => $validated['full_name'] ?? null,
                'is_admin' => User::query()->count() === 0,
                'password' => $validated['password'],
                'mobile_verified_at' => now(),
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.auth.register_failed_duplicate'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->forgetRegisterSession($request);

        return redirect()->route('dashboard')->with('status', __('messages.auth.account_created_otp'));
    }

    private function validateRegisterMobilePayload(Request $request): array
    {
        return $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
        ]);
    }

    private function validateRegisterDetailsPayload(Request $request): array
    {
        return $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:32', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function forgetRegisterSession(Request $request): void
    {
        $request->session()->forget([
            self::REGISTER_MOBILE_SESSION_KEY,
            self::REGISTER_VERIFIED_SESSION_KEY,
        ]);
    }
}

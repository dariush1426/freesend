<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailVerificationService;
use App\Support\MobileNumber;
use App\Support\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileVerificationController extends Controller
{
    public function requestEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => __('messages.verification.already_verified'),
                'verified' => true,
            ]);
        }

        try {
            $ttl = EmailVerificationService::send($user);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('messages.verification.email_code_sent', ['minutes' => $ttl]),
            'verified' => false,
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => __('messages.verification.already_verified'),
                'verified' => true,
            ]);
        }

        if (! EmailVerificationService::verify($user, trim($validated['code']))) {
            throw ValidationException::withMessages([
                'code' => __('messages.verification.code_invalid'),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json([
            'message' => __('messages.verification.email_verified'),
            'verified' => true,
        ]);
    }

    public function requestMobile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $mobile = MobileNumber::normalize((string) $user->mobile);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.verification.mobile_missing'),
            ]);
        }

        if ($user->mobile_verified_at) {
            return response()->json([
                'message' => __('messages.verification.already_verified'),
                'verified' => true,
            ]);
        }

        try {
            $ttl = OtpService::send($mobile, 'profile_mobile_verify', [
                'user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'mobile' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('messages.verification.mobile_code_sent', ['minutes' => $ttl]),
            'verified' => false,
        ]);
    }

    public function verifyMobile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $mobile = MobileNumber::normalize((string) $user->mobile);

        if (! $mobile) {
            throw ValidationException::withMessages([
                'mobile' => __('messages.verification.mobile_missing'),
            ]);
        }

        if ($user->mobile_verified_at) {
            return response()->json([
                'message' => __('messages.verification.already_verified'),
                'verified' => true,
            ]);
        }

        if (! OtpService::verify($mobile, 'profile_mobile_verify', trim($validated['code']))) {
            throw ValidationException::withMessages([
                'code' => __('messages.verification.code_invalid'),
            ]);
        }

        $user->forceFill(['mobile_verified_at' => now()])->save();

        return response()->json([
            'message' => __('messages.verification.mobile_verified'),
            'verified' => true,
        ]);
    }
}

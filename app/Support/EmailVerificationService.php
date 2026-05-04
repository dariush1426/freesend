<?php

namespace App\Support;

use App\Models\EmailVerificationCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailVerificationService
{
    public static function send(User $user, string $purpose = 'profile_email_verify'): int
    {
        $email = trim((string) $user->email);

        if ($email === '') {
            throw new RuntimeException(__('messages.verification.email_missing'));
        }

        $resendSeconds = max(15, (int) Setting::getValue('sms_otp_resend_seconds', '60'));
        $ttlMinutes = max(1, (int) Setting::getValue('sms_otp_ttl_minutes', '2'));
        $length = min(8, max(4, (int) Setting::getValue('sms_otp_length', '6')));

        $latest = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if ($latest && $latest->created_at && $latest->created_at->addSeconds($resendSeconds)->isFuture()) {
            $seconds = now()->diffInSeconds($latest->created_at->addSeconds($resendSeconds), false);
            throw new RuntimeException(__('messages.otp.wait_before_resend', ['seconds' => $seconds]));
        }

        $code = self::generateCode($length);

        $record = EmailVerificationCode::create([
            'user_id' => $user->id,
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        try {
            MailSettings::apply();

            Mail::raw(
                __('messages.verification.email_body', [
                    'code' => $code,
                    'minutes' => $ttlMinutes,
                    'app' => Setting::getValue('app_display_name', config('app.name', 'FreeSend')),
                ]),
                function ($message) use ($email): void {
                    $message
                        ->to($email)
                        ->subject(__('messages.verification.email_subject'));
                }
            );
        } catch (\Throwable $exception) {
            $record->delete();
            throw $exception;
        }

        return $ttlMinutes;
    }

    public static function verify(User $user, string $code, string $purpose = 'profile_email_verify'): bool
    {
        $email = trim((string) $user->email);

        if ($email === '') {
            return false;
        }

        $maxAttempts = max(1, (int) Setting::getValue('sms_otp_max_attempts', '5'));

        $record = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $record) {
            return false;
        }

        if ($record->expires_at->isPast()) {
            return false;
        }

        if ($record->attempts >= $maxAttempts) {
            return false;
        }

        $record->forceFill(['attempts' => $record->attempts + 1])->save();

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    private static function generateCode(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}

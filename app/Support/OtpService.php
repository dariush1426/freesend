<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SmsOtp;
use App\Support\Sms\SmsManager;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class OtpService
{
    public static function send(string $mobile, string $purpose, array $meta = []): int
    {
        if (Setting::getValue('sms_otp_enabled', 'true') !== 'true') {
            throw new RuntimeException(__('messages.otp.disabled'));
        }

        $resendSeconds = max(15, (int) Setting::getValue('sms_otp_resend_seconds', '60'));
        $ttlMinutes = max(1, (int) Setting::getValue('sms_otp_ttl_minutes', '2'));
        $length = min(8, max(4, (int) Setting::getValue('sms_otp_length', '6')));

        $latest = SmsOtp::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if ($latest && $latest->created_at && $latest->created_at->addSeconds($resendSeconds)->isFuture()) {
            $seconds = now()->diffInSeconds($latest->created_at->addSeconds($resendSeconds), false);
            throw new RuntimeException(__('messages.otp.wait_before_resend', ['seconds' => $seconds]));
        }

        $code = self::generateCode($length);

        $otp = SmsOtp::create([
            'mobile' => $mobile,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'meta' => $meta,
        ]);

        try {
            SmsManager::sendOtp($mobile, $code);
        } catch (\Throwable $exception) {
            $otp->delete();
            throw $exception;
        }

        return $ttlMinutes;
    }

    public static function verify(string $mobile, string $purpose, string $code): bool
    {
        $maxAttempts = max(1, (int) Setting::getValue('sms_otp_max_attempts', '5'));

        $otp = SmsOtp::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            return false;
        }

        if ($otp->attempts >= $maxAttempts) {
            return false;
        }

        $otp->forceFill(['attempts' => $otp->attempts + 1])->save();

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    private static function generateCode(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}

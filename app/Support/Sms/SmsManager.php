<?php

namespace App\Support\Sms;

use App\Models\Setting;

class SmsManager
{
    public static function send(string $mobile, string $message): void
    {
        self::provider()->send($mobile, $message);
    }

    public static function sendOtp(string $mobile, string $code): void
    {
        self::provider()->sendOtp($mobile, $code);
    }

    private static function provider(): SmsProvider
    {
        $driver = trim((string) Setting::getValue('sms_driver', 'log'));

        return match ($driver) {
            'smsir' => new SmsIrProvider(),
            default => new LogSmsProvider(),
        };
    }
}

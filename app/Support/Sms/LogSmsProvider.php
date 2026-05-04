<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProvider
{
    public function send(string $mobile, string $message): void
    {
        Log::info('SMS(log) sent', [
            'mobile' => $mobile,
            'message' => $message,
        ]);
    }

    public function sendOtp(string $mobile, string $code): void
    {
        Log::info('SMS OTP(log) sent', [
            'mobile' => $mobile,
            'code' => $code,
        ]);
    }
}

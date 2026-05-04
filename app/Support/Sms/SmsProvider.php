<?php

namespace App\Support\Sms;

interface SmsProvider
{
    public function send(string $mobile, string $message): void;

    public function sendOtp(string $mobile, string $code): void;
}

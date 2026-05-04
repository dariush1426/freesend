<?php

namespace App\Support\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsIrProvider implements SmsProvider
{
    public function send(string $mobile, string $message): void
    {
        $apiKey = trim((string) Setting::getValue('smsir_api_key', ''));
        $lineNumber = trim((string) Setting::getValue('smsir_line_number', ''));

        if ($apiKey === '' || $lineNumber === '') {
            throw new RuntimeException(__('messages.sms.config_text_incomplete'));
        }

        $endpoint = $this->resolveEndpoint((string) Setting::getValue('smsir_message_endpoint', '/send/bulk'));
        $payload = [
            'lineNumber' => $lineNumber,
            'messageText' => $message,
            'mobiles' => [$this->toInternational($mobile)],
        ];

        $response = $this->httpClient()
            ->withHeaders(['x-api-key' => $apiKey])
            ->timeout(15)
            ->retry(2, 300)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(__('messages.sms.send_failed', ['response' => $response->body()]));
        }
    }

    public function sendOtp(string $mobile, string $code): void
    {
        $apiKey = trim((string) Setting::getValue('smsir_api_key', ''));
        $templateId = trim((string) Setting::getValue('smsir_otp_template_id', ''));
        $parameterName = trim((string) Setting::getValue('smsir_otp_parameter_name', 'Code'));
        $fallbackEnabled = Setting::getValue('smsir_otp_fallback_enabled', 'true') === 'true';

        if ($apiKey === '') {
            throw new RuntimeException(__('messages.sms.config_otp_api_incomplete'));
        }

        if ($templateId === '') {
            if (! $fallbackEnabled) {
                throw new RuntimeException(__('messages.sms.config_otp_template_incomplete'));
            }

            $messageTemplate = (string) Setting::getValue('sms_otp_message_template', __('messages.sms.otp_template_default'));
            $message = str_replace(':code', $code, $messageTemplate);
            $this->send($mobile, $message);

            return;
        }

        $endpoint = $this->resolveEndpoint((string) Setting::getValue('smsir_verify_endpoint', '/send/verify'));
        $payload = [
            'mobile' => $this->toInternational($mobile),
            'templateId' => (int) $templateId,
            'parameters' => [
                [
                    'name' => $parameterName,
                    'value' => $code,
                ],
            ],
        ];

        $response = $this->httpClient()
            ->withHeaders(['x-api-key' => $apiKey])
            ->timeout(15)
            ->retry(2, 300)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(__('messages.sms.otp_send_failed', ['response' => $response->body()]));
        }
    }

    private function resolveEndpoint(string $path): string
    {
        $baseUrl = rtrim((string) Setting::getValue('smsir_base_url', 'https://api.sms.ir/v1'), '/');
        $path = '/'.ltrim($path, '/');

        return $baseUrl.$path;
    }

    private function toInternational(string $mobile): string
    {
        if (str_starts_with($mobile, '09')) {
            return '98'.substr($mobile, 1);
        }

        return $mobile;
    }

    private function httpClient()
    {
        $verify = $this->resolveVerifyOption();

        return Http::acceptJson()->withOptions(['verify' => $verify]);
    }

    private function resolveVerifyOption(): bool|string
    {
        $sslVerify = Setting::getValue('sms_ssl_verify', 'true') === 'true';
        $caBundlePath = trim((string) Setting::getValue('sms_ca_bundle_path', ''));

        if (! $sslVerify) {
            return false;
        }

        if ($caBundlePath !== '') {
            return $caBundlePath;
        }

        return true;
    }
}

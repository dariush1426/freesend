<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\MobileNumber;
use App\Support\Sms\SmsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function edit(): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        return view('admin.settings', [
            'settings' => $this->settingsWithValues(),
            'mailerOptions' => [
                'smtp' => __('admin.options.mailer.smtp'),
                'log' => __('admin.options.mailer.log'),
                'failover' => __('admin.options.mailer.failover'),
            ],
            'encryptionOptions' => [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'none' => __('admin.options.encryption.none'),
            ],
            'smsDriverOptions' => [
                'log' => __('admin.options.sms_driver.log'),
                'smsir' => 'SMS.ir',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $request->validate([
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:200'],
            'allowed_extensions' => ['required', 'string', 'max:255'],
            'default_expire_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'quick_send_enabled' => ['nullable', 'boolean'],
            'quick_send_max_file_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'quick_send_default_expire_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'email_notification_enabled' => ['nullable', 'boolean'],
            'sms_otp_enabled' => ['nullable', 'boolean'],
            'sms_notification_enabled' => ['nullable', 'boolean'],
            'public_link_enabled' => ['nullable', 'boolean'],
            'mail_mailer' => ['required', 'string', 'in:smtp,log,failover'],
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:120'],
            'zibal_enabled' => ['nullable', 'boolean'],
            'zibal_test_mode' => ['nullable', 'boolean'],
            'zibal_merchant' => ['nullable', 'string', 'max:120'],
            'zibal_request_url' => ['required', 'string', 'max:255'],
            'zibal_start_url' => ['required', 'string', 'max:255'],
            'zibal_verify_url' => ['required', 'string', 'max:255'],
            'sms_driver' => ['required', 'string', 'in:log,smsir'],
            'sms_otp_ttl_minutes' => ['required', 'integer', 'min:1', 'max:30'],
            'sms_otp_length' => ['required', 'integer', 'min:4', 'max:8'],
            'sms_otp_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'sms_otp_resend_seconds' => ['required', 'integer', 'min:15', 'max:600'],
            'smsir_base_url' => ['required', 'string', 'max:255'],
            'smsir_api_key' => ['nullable', 'string', 'max:255'],
            'smsir_otp_template_id' => ['nullable', 'string', 'max:40'],
            'smsir_otp_parameter_name' => ['required', 'string', 'max:60'],
            'smsir_otp_fallback_enabled' => ['nullable', 'boolean'],
            'sms_otp_message_template' => ['required', 'string', 'max:255'],
            'smsir_line_number' => ['nullable', 'string', 'max:40'],
            'smsir_verify_endpoint' => ['required', 'string', 'max:120'],
            'smsir_message_endpoint' => ['required', 'string', 'max:120'],
            'sms_ssl_verify' => ['nullable', 'boolean'],
            'sms_ca_bundle_path' => ['nullable', 'string', 'max:255'],
            'preview_enabled' => ['nullable', 'boolean'],
            'preview_pdf_enabled' => ['nullable', 'boolean'],
            'preview_max_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'preview_image_extensions' => ['required', 'string', 'max:255'],
            'chunk_upload_threshold_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'chunk_upload_size_mb' => ['required', 'integer', 'min:1', 'max:20'],
            'chunk_upload_max_mb_per_minute' => ['required', 'integer', 'min:1', 'max:5000'],
            'security_scan_enabled' => ['nullable', 'boolean'],
            'security_scan_driver' => ['required', 'string', 'in:basic'],
            'security_blocked_extensions' => ['required', 'string', 'max:255'],
            'app_display_name' => ['required', 'string', 'max:80'],
            'app_short_name' => ['required', 'string', 'max:40'],
            'pwa_enabled' => ['nullable', 'boolean'],
            'pwa_install_popup_enabled' => ['nullable', 'boolean'],
            'pwa_theme_color' => ['required', 'string', 'max:20'],
            'pwa_background_color' => ['required', 'string', 'max:20'],
            'pwa_logo_desktop' => ['nullable', 'file', 'image', 'max:4096'],
            'pwa_logo_mobile' => ['nullable', 'file', 'image', 'max:4096'],
            'pwa_logo_retina' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        foreach (Setting::DEFAULTS as $key => $default) {
            $value = match ($key) {
                'email_notification_enabled',
                'quick_send_enabled',
                'sms_otp_enabled',
                'sms_notification_enabled',
                'public_link_enabled',
                'sms_ssl_verify',
                'smsir_otp_fallback_enabled',
                'preview_enabled',
                'preview_pdf_enabled',
                'security_scan_enabled',
                'pwa_enabled',
                'pwa_install_popup_enabled',
                'zibal_enabled',
                'zibal_test_mode' => $request->boolean($key) ? 'true' : 'false',
                'mail_password' => trim((string) ($validated['mail_password'] ?? '')) !== ''
                    ? (string) $validated['mail_password']
                    : (string) Setting::getValue('mail_password', $default),
                'pwa_logo_desktop_path',
                'pwa_logo_mobile_path',
                'pwa_logo_retina_path' => (string) Setting::getValue($key, $default),
                default => (string) ($validated[$key] ?? $default),
            };

            Setting::setValue($key, $value);
        }

        $this->storePwaLogo($request, 'pwa_logo_desktop', 'pwa_logo_desktop_path');
        $this->storePwaLogo($request, 'pwa_logo_mobile', 'pwa_logo_mobile_path');
        $this->storePwaLogo($request, 'pwa_logo_retina', 'pwa_logo_retina_path');

        return back()->with('status', __('messages.admin.settings_saved'));
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            MailSettings::apply();

            Mail::raw(
                'This is a test email from FreeSend. Sent at: '.now()->toDateTimeString(),
                function ($message) use ($validated): void {
                    $message
                        ->to($validated['test_email'])
                        ->subject('FreeSend Test Email');
                }
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'test_email' => __('messages.admin.test_email_failed', ['message' => $exception->getMessage()]),
            ]);
        }

        return back()->with('status', __('messages.admin.test_email_sent'));
    }

    public function sendTestSms(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $request->validate([
            'test_sms_mobile' => ['required', 'string', 'max:20'],
        ]);

        $mobile = MobileNumber::normalize($validated['test_sms_mobile']);

        if (! $mobile) {
            return back()->withErrors([
                'test_sms_mobile' => __('messages.admin.test_sms_mobile_invalid'),
            ]);
        }

        try {
            SmsManager::send($mobile, 'FreeSend test SMS at '.now()->toDateTimeString());
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, 'cURL error 60')) {
                $message .= ' | '.__('messages.admin.ca_bundle_hint');
            }

            return back()->withErrors([
                'test_sms_mobile' => __('messages.admin.test_sms_failed', ['message' => $message]),
            ]);
        }

        return back()->with('status', __('messages.admin.test_sms_sent'));
    }

    private function settingsWithValues(): array
    {
        return collect(Setting::DEFAULTS)
            ->mapWithKeys(fn (string $value, string $key) => [$key => Setting::getValue($key, $value)])
            ->all();
    }

    private function storePwaLogo(Request $request, string $inputKey, string $settingKey): void
    {
        $file = $request->file($inputKey);

        if (! $file) {
            return;
        }

        $oldPath = (string) Setting::getValue($settingKey, '');
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs('pwa/logos', $settingKey.'_'.time().'.'.$extension);

        Setting::setValue($settingKey, $path);

        if ($oldPath !== '' && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
        }
    }
}

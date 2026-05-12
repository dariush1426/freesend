<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const TRANSLATED_DEFAULTS = [
        'sms_otp_message_template' => 'messages.sms.otp_template_default',
    ];

    public const DEFAULTS = [
        'max_file_size_mb' => '20',
        'allowed_extensions' => 'jpg,png,pdf,docx,xlsx,zip',
        'default_expire_hours' => '24',
        'quick_send_enabled' => 'true',
        'quick_send_max_file_size_mb' => '10',
        'quick_send_default_expire_hours' => '1',
        'email_notification_enabled' => 'true',
        'sms_otp_enabled' => 'true',
        'sms_notification_enabled' => 'false',
        'public_link_enabled' => 'false',
        'mail_mailer' => 'log',
        'mail_host' => '127.0.0.1',
        'mail_port' => '2525',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => 'noreply@freesend.local',
        'mail_from_name' => 'FreeSend',
        'zibal_enabled' => 'false',
        'zibal_test_mode' => 'true',
        'zibal_merchant' => '',
        'zibal_request_url' => 'https://gateway.zibal.ir/v1/request',
        'zibal_start_url' => 'https://gateway.zibal.ir/start',
        'zibal_verify_url' => 'https://gateway.zibal.ir/v1/verify',
        'sms_driver' => 'log',
        'sms_otp_ttl_minutes' => '2',
        'sms_otp_length' => '6',
        'sms_otp_max_attempts' => '5',
        'sms_otp_resend_seconds' => '60',
        'smsir_base_url' => 'https://api.sms.ir/v1',
        'smsir_api_key' => '',
        'smsir_otp_template_id' => '',
        'smsir_otp_parameter_name' => 'Code',
        'smsir_otp_fallback_enabled' => 'true',
        'sms_otp_message_template' => 'FreeSend verification code: :code',
        'smsir_line_number' => '',
        'smsir_verify_endpoint' => '/send/verify',
        'smsir_message_endpoint' => '/send/bulk',
        'sms_ssl_verify' => 'true',
        'sms_ca_bundle_path' => '',
        'preview_enabled' => 'true',
        'preview_pdf_enabled' => 'true',
        'preview_max_size_mb' => '12',
        'preview_image_extensions' => 'jpg,jpeg,png,gif,webp,bmp',
        'chunk_upload_threshold_mb' => '8',
        'chunk_upload_size_mb' => '2',
        'chunk_upload_max_mb_per_minute' => '80',
        'security_scan_enabled' => 'true',
        'security_scan_driver' => 'basic',
        'security_blocked_extensions' => 'exe,bat,cmd,com,scr,msi,ps1,vbs,js,jar,apk',
        'app_display_name' => 'FreeSend',
        'app_short_name' => 'FreeSend',
        'pwa_enabled' => 'true',
        'pwa_install_popup_enabled' => 'true',
        'pwa_theme_color' => '#0f766e',
        'pwa_background_color' => '#eef2f7',
        'pwa_logo_desktop_path' => '',
        'pwa_logo_mobile_path' => '',
        'pwa_logo_retina_path' => '',
        'landing_hero_badge' => 'FreeSend launch preview',
        'landing_hero_title' => 'ارسال فایل سریع، امن و قابل پیگیری',
        'landing_hero_body' => 'FreeSend مسیر ارسال، دریافت، پیش نمایش و نگهداری فایل های مهم را در یک تجربه ساده و قابل اعتماد کنار هم می آورد.',
        'landing_primary_cta' => 'ارسال سریع فایل',
        'landing_secondary_cta' => 'ساخت حساب رایگان',
        'landing_hero_heading_tag' => 'h2',
        'landing_hero_title_size' => '44',
        'landing_hero_title_weight' => '800',
        'landing_hero_image_path' => '',
        'landing_features_title' => 'قابلیت های برجسته FreeSend',
        'landing_features_body' => 'نسخه لانچ روی مسیرهای اصلی و ملموس تمرکز دارد: ارسال، امنیت، پیش نمایش و فضای شخصی.',
        'landing_section_title_size' => '32',
        'landing_section_title_weight' => '800',
        'landing_feature_1_title' => 'ارسال سریع برای مهمان ها',
        'landing_feature_1_body' => 'بدون ساخت حساب، فایل را برای کاربر ثبت شده بفرستید و بعد از ارسال مسیر ثبت نام آماده را ببینید.',
        'landing_feature_1_image_path' => '',
        'landing_feature_2_title' => 'امنیت قابل فهم',
        'landing_feature_2_body' => 'رمز دانلود، زمان انقضا، لینک عمومی و وضعیت اسکن امنیتی در همان جریان کاربر دیده می شوند.',
        'landing_feature_2_image_path' => '',
        'landing_feature_3_title' => 'فضای شخصی سبک',
        'landing_feature_3_body' => 'فایل های مهم را با پوشه ها، فیلترها، حالت list/grid و پیش نمایش سریع مدیریت کنید.',
        'landing_feature_3_image_path' => '',
    ];

    public static function getValue(string $key, ?string $fallback = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting?->value !== null) {
            return $setting->value;
        }

        if (isset(self::TRANSLATED_DEFAULTS[$key])) {
            return __(self::TRANSLATED_DEFAULTS[$key]);
        }

        return self::DEFAULTS[$key] ?? $fallback;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

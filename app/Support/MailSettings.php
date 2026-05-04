<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;

class MailSettings
{
    public static function apply(): void
    {
        $mailer = Setting::getValue('mail_mailer', (string) config('mail.default', 'log'));
        $host = Setting::getValue('mail_host', (string) config('mail.mailers.smtp.host', '127.0.0.1'));
        $port = (int) Setting::getValue('mail_port', (string) config('mail.mailers.smtp.port', 2525));
        $username = Setting::getValue('mail_username', (string) config('mail.mailers.smtp.username', ''));
        $password = Setting::getValue('mail_password', (string) config('mail.mailers.smtp.password', ''));
        $encryption = self::normalizeEncryption(Setting::getValue('mail_encryption', (string) config('mail.mailers.smtp.scheme', '')));
        $fromAddress = Setting::getValue('mail_from_address', (string) config('mail.from.address', 'noreply@freesend.local'));
        $fromName = Setting::getValue('mail_from_name', (string) config('mail.from.name', 'FreeSend'));

        Config::set([
            'mail.default' => $mailer ?: 'log',
            'mail.mailers.smtp.host' => $host ?: '127.0.0.1',
            'mail.mailers.smtp.port' => $port > 0 ? $port : 2525,
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.scheme' => $encryption,
            'mail.from.address' => $fromAddress ?: 'noreply@freesend.local',
            'mail.from.name' => $fromName ?: 'FreeSend',
        ]);

        app('mail.manager')->forgetMailers();
    }

    private static function normalizeEncryption(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || $normalized === 'none') {
            return null;
        }

        return in_array($normalized, ['tls', 'ssl'], true) ? $normalized : null;
    }
}

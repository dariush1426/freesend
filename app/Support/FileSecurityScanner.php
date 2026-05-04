<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SharedFile;
use Illuminate\Support\Facades\Storage;

class FileSecurityScanner
{
    public static function scan(SharedFile $file): array
    {
        $blockedExtensions = collect(explode(',', (string) Setting::getValue('security_blocked_extensions', '')))
            ->map(fn (string $ext) => trim(mb_strtolower($ext)))
            ->filter()
            ->values()
            ->all();

        $extension = mb_strtolower((string) ($file->extension ?? ''));

        if (in_array($extension, $blockedExtensions, true)) {
            return [
                'safe' => false,
                'message' => __('messages.security_scan.blocked_extension'),
            ];
        }

        if (! Storage::exists($file->storage_path)) {
            return [
                'safe' => false,
                'message' => __('messages.security_scan.file_missing'),
            ];
        }

        $path = Storage::path($file->storage_path);
        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return [
                'safe' => false,
                'message' => __('messages.security_scan.file_read_failed'),
            ];
        }

        $content = fread($handle, 1024 * 512) ?: '';
        fclose($handle);

        if (str_contains($content, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
            return [
                'safe' => false,
                'message' => __('messages.security_scan.eicar_detected'),
            ];
        }

        return [
            'safe' => true,
            'message' => __('messages.security_scan.clean'),
        ];
    }
}

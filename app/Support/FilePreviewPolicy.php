<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SharedFile;

class FilePreviewPolicy
{
    public static function fromSettings(): array
    {
        return [
            'enabled' => Setting::getValue('preview_enabled', 'true') === 'true',
            'pdf_enabled' => Setting::getValue('preview_pdf_enabled', 'true') === 'true',
            'max_size_bytes' => max(1, (int) Setting::getValue('preview_max_size_mb', '12')) * 1024 * 1024,
            'image_extensions' => collect(explode(',', (string) Setting::getValue('preview_image_extensions', 'jpg,jpeg,png,gif,webp,bmp')))
                ->map(fn (string $ext) => trim(mb_strtolower($ext)))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public static function detectType(SharedFile $file, array $policy): string
    {
        $ext = mb_strtolower((string) ($file->extension ?? ''));

        if (in_array($ext, $policy['image_extensions'] ?? [], true)) {
            return 'image';
        }

        if ($ext === 'pdf') {
            return 'pdf';
        }

        return 'other';
    }

    public static function canPreview(SharedFile $file, array $policy): bool
    {
        if (! ($policy['enabled'] ?? false)) {
            return false;
        }

        if (! $file->isPreviewableFileAvailable()) {
            return false;
        }

        if ((int) $file->size > (int) ($policy['max_size_bytes'] ?? 0)) {
            return false;
        }

        $type = self::detectType($file, $policy);

        if ($type === 'image') {
            return true;
        }

        if ($type === 'pdf') {
            return (bool) ($policy['pdf_enabled'] ?? false);
        }

        return false;
    }
}

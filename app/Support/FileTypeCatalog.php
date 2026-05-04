<?php

namespace App\Support;

class FileTypeCatalog
{
    public static function categories(): array
    {
        return ['all', 'image', 'video', 'document', 'archive'];
    }

    public static function rule(string $category): array
    {
        return match ($category) {
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif'],
                'mime_prefixes' => ['image/'],
            ],
            'video' => [
                'extensions' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', '3gp', 'ogv'],
                'mime_prefixes' => ['video/'],
            ],
            'document' => [
                'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'odt', 'ods', 'odp'],
                'mime_prefixes' => ['text/', 'application/pdf'],
            ],
            'archive' => [
                'extensions' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
                'mime_prefixes' => ['application/zip', 'application/x-rar', 'application/x-7z', 'application/gzip'],
            ],
            default => [
                'extensions' => [],
                'mime_prefixes' => [],
            ],
        };
    }

    public static function detect(?string $extension, ?string $mimeType = null): string
    {
        $extension = mb_strtolower((string) $extension);
        $mimeType = mb_strtolower((string) $mimeType);

        foreach (['image', 'video', 'document', 'archive'] as $category) {
            $rule = self::rule($category);

            if (in_array($extension, $rule['extensions'], true)) {
                return $category;
            }

            foreach ($rule['mime_prefixes'] as $prefix) {
                if ($prefix !== '' && str_starts_with($mimeType, $prefix)) {
                    return $category;
                }
            }
        }

        return 'other';
    }
}

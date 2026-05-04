<?php

namespace App\Models;

use App\Support\FileTypeCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'is_personal_storage',
    'original_name',
    'stored_name',
    'mime_type',
    'extension',
    'size',
    'storage_path',
    'checksum',
    'expires_at',
    'status',
    'download_password_hash',
    'security_scan_status',
    'security_scan_message',
    'security_scanned_at',
])]
class SharedFile extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DELETED = 'deleted';
    public const SECURITY_SCAN_PENDING = 'pending';
    public const SECURITY_SCAN_CLEAN = 'clean';
    public const SECURITY_SCAN_INFECTED = 'infected';
    public const SECURITY_SCAN_FAILED = 'failed';

    protected $table = 'files';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'security_scanned_at' => 'datetime',
            'is_personal_storage' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(FileSend::class, 'file_id');
    }

    public function readableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public function category(): string
    {
        return FileTypeCatalog::detect($this->extension, $this->mime_type);
    }

    public function isExpiredByTime(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->isExpiredByTime()
            && $this->isSecurityApproved();
    }

    public function isPasswordProtected(): bool
    {
        return ! empty($this->download_password_hash);
    }

    public function isSecurityApproved(): bool
    {
        return in_array((string) $this->security_scan_status, ['', self::SECURITY_SCAN_CLEAN], true);
    }

    public function isSecurityScanPending(): bool
    {
        return (string) $this->security_scan_status === self::SECURITY_SCAN_PENDING;
    }
}

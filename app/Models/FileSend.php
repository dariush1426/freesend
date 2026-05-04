<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'file_id',
    'sender_id',
    'sender_name',
    'sender_contact',
    'receiver_id',
    'message',
    'read_at',
    'downloaded_at',
    'public_token',
    'public_link_enabled',
    'public_link_expires_at',
    'public_max_downloads',
    'public_download_count',
    'public_last_downloaded_at',
])]
class FileSend extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'public_link_enabled' => 'boolean',
            'public_link_expires_at' => 'datetime',
            'public_max_downloads' => 'integer',
            'public_download_count' => 'integer',
            'public_last_downloaded_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class, 'file_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function isGuestSender(): bool
    {
        return $this->sender_id === null;
    }

    public function senderDisplayName(): string
    {
        if ($this->sender) {
            return (string) $this->sender->username;
        }

        $guestName = trim((string) $this->sender_name);

        return $guestName !== ''
            ? $guestName
            : __('ui.quick_send.guest_sender_fallback');
    }

    public function senderDisplayContact(): ?string
    {
        if ($this->sender) {
            return $this->sender->mobile ?: $this->sender->email;
        }

        $guestContact = trim((string) $this->sender_contact);

        return $guestContact !== '' ? $guestContact : null;
    }

    public function isPublicLinkExpired(): bool
    {
        return $this->public_link_expires_at !== null && $this->public_link_expires_at->isPast();
    }

    public function hasPublicDownloadLimitReached(): bool
    {
        return $this->public_max_downloads !== null
            && $this->public_download_count >= $this->public_max_downloads;
    }

    public static function generatePublicToken(int $length = 10): string
    {
        $attempts = 0;

        do {
            $token = Str::lower(Str::random($length));
            $exists = static::query()->where('public_token', $token)->exists();
            $attempts++;
        } while ($exists && $attempts < 8);

        return $exists ? Str::lower(Str::random(16)) : $token;
    }
}

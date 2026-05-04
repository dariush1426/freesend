<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'title', 'body', 'payload', 'read_at'])]
class AppNotification extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTitleAttribute(?string $value): string
    {
        return $this->translatedField('title_key', 'title_params', $value);
    }

    public function getBodyAttribute(?string $value): string
    {
        return $this->translatedField('body_key', 'body_params', $value);
    }

    private function translatedField(string $keyField, string $paramsField, ?string $fallback): string
    {
        $payload = $this->payload;

        if (! is_array($payload)) {
            return (string) $fallback;
        }

        $translationKey = $payload[$keyField] ?? null;
        $translationParams = $payload[$paramsField] ?? [];

        if (! is_string($translationKey) || $translationKey === '') {
            return (string) $fallback;
        }

        return __($translationKey, is_array($translationParams) ? $translationParams : []);
    }
}

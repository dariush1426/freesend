<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'file_id',
    'user_id',
    'role',
    'context',
    'folder_id',
    'is_starred',
])]
class FileStorageAccess extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_VIEWER = 'viewer';

    public const CONTEXT_OWNED = 'owned';
    public const CONTEXT_SENT = 'sent';
    public const CONTEXT_RECEIVED = 'received';

    protected $table = 'file_storage_access';

    protected $casts = [
        'is_starred' => 'boolean',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class, 'file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'folder_id');
    }

    public function canManage(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_MANAGER], true);
    }
}

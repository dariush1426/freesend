<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'mobile', 'full_name', 'avatar', 'allow_receive_no_expiry', 'is_admin', 'password', 'email_verified_at', 'mobile_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'allow_receive_no_expiry' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function ownedFiles(): HasMany
    {
        return $this->hasMany(SharedFile::class, 'owner_id');
    }

    public function personalStorageFiles(): HasMany
    {
        return $this->hasMany(SharedFile::class, 'owner_id')
            ->where('is_personal_storage', true);
    }

    public function storageAccesses(): HasMany
    {
        return $this->hasMany(FileStorageAccess::class, 'user_id');
    }

    public function accessibleStorageFiles(): BelongsToMany
    {
        return $this->belongsToMany(SharedFile::class, 'file_storage_access', 'user_id', 'file_id')
            ->withPivot(['role', 'context'])
            ->withTimestamps();
    }

    public function storageFolders(): HasMany
    {
        return $this->hasMany(StorageFolder::class, 'owner_id');
    }

    public function sentFiles(): HasMany
    {
        return $this->hasMany(FileSend::class, 'sender_id');
    }

    public function receivedFiles(): HasMany
    {
        return $this->hasMany(FileSend::class, 'receiver_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)->latestOfMany();
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }
}

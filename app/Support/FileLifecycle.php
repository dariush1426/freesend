<?php

namespace App\Support;

use App\Models\SharedFile;
use Illuminate\Support\Facades\Storage;

class FileLifecycle
{
    public static function markExpiredFiles(): int
    {
        return SharedFile::query()
            ->where('status', SharedFile::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => SharedFile::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    public static function cleanupExpiredFiles(bool $dryRun = false): array
    {
        $expiredMarked = self::markExpiredFiles();

        $query = SharedFile::query()
            ->where('status', SharedFile::STATUS_EXPIRED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $candidates = (clone $query)->count();
        $deletedFromStorage = 0;
        $markedDeleted = 0;

        if (! $dryRun) {
            $query->orderBy('id')->chunkById(100, function ($files) use (&$deletedFromStorage, &$markedDeleted): void {
                foreach ($files as $file) {
                    if ($file->storage_path !== '' && Storage::exists($file->storage_path)) {
                        if (Storage::delete($file->storage_path)) {
                            $deletedFromStorage++;
                        }
                    }

                    $file->forceFill(['status' => SharedFile::STATUS_DELETED])->save();
                    $markedDeleted++;
                }
            });
        }

        return [
            'expired_marked' => $expiredMarked,
            'cleanup_candidates' => $candidates,
            'storage_deleted' => $deletedFromStorage,
            'records_marked_deleted' => $markedDeleted,
            'dry_run' => $dryRun,
        ];
    }
}

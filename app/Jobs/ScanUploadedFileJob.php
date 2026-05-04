<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\SharedFile;
use App\Support\FileSecurityScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanUploadedFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $fileId)
    {
    }

    public function handle(): void
    {
        $file = SharedFile::query()->find($this->fileId);

        if (! $file) {
            return;
        }

        if (Setting::getValue('security_scan_enabled', 'true') !== 'true') {
            $file->forceFill([
                'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
                'security_scan_message' => __('messages.security_scan.disabled'),
                'security_scanned_at' => now(),
            ])->save();

            return;
        }

        try {
            $result = FileSecurityScanner::scan($file);

            if (! ($result['safe'] ?? false)) {
                if (Storage::exists($file->storage_path)) {
                    Storage::delete($file->storage_path);
                }

                $file->forceFill([
                    'status' => SharedFile::STATUS_DELETED,
                    'security_scan_status' => SharedFile::SECURITY_SCAN_INFECTED,
                    'security_scan_message' => (string) ($result['message'] ?? __('messages.security_scan.rejected_default')),
                    'security_scanned_at' => now(),
                ])->save();

                return;
            }

            $file->forceFill([
                'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
                'security_scan_message' => (string) ($result['message'] ?? __('messages.security_scan.success')),
                'security_scanned_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Security scan failed', [
                'file_id' => $file->id,
                'message' => $exception->getMessage(),
            ]);

            $file->forceFill([
                'security_scan_status' => SharedFile::SECURITY_SCAN_FAILED,
                'security_scan_message' => __('messages.security_scan.failed', ['message' => $exception->getMessage()]),
                'security_scanned_at' => now(),
            ])->save();
        }
    }
}

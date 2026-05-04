<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\SharedFile;
use App\Support\FilePreviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilePreviewController extends Controller
{
    public function __invoke(Request $request, FileSend $fileSend): StreamedResponse|RedirectResponse
    {
        $fileSend->load('file');
        $file = $fileSend->file;
        $userId = Auth::id();

        abort_unless(
            $fileSend->receiver_id === $userId || $fileSend->sender_id === $userId,
            403
        );

        if ($file->status === SharedFile::STATUS_ACTIVE && $file->isExpiredByTime()) {
            $file->forceFill(['status' => SharedFile::STATUS_EXPIRED])->save();
            $file->refresh();
        }

        if ($file->isSecurityScanPending()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_pending')]);
        }

        if (! $file->isSecurityApproved()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_security')]);
        }

        if (! $file->isDownloadable()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_unavailable')]);
        }

        if ($file->isPasswordProtected() && ! $this->isUnlocked($request, $file)) {
            return redirect()->route('file-sends.unlock', $fileSend);
        }

        $policy = FilePreviewPolicy::fromSettings();

        if (! FilePreviewPolicy::canPreview($file, $policy)) {
            return back()->withErrors(['preview' => __('ui.errors.preview_policy')]);
        }

        if (! Storage::exists($file->storage_path)) {
            return back()->withErrors(['preview' => __('ui.errors.preview_missing')]);
        }

        return Storage::response(
            $file->storage_path,
            $file->original_name,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream'],
            'inline'
        );
    }

    private function isUnlocked(Request $request, SharedFile $file): bool
    {
        return (bool) $request->session()->get('download_password_unlocked_'.$file->id, false);
    }
}

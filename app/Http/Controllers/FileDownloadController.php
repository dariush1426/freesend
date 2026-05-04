<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\SharedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileDownloadController extends Controller
{
    public function __invoke(Request $request, FileSend $fileSend): StreamedResponse|RedirectResponse
    {
        $fileSend->load('file');
        $userId = Auth::id();
        $file = $fileSend->file;

        abort_unless(
            $fileSend->receiver_id === $userId || $fileSend->sender_id === $userId,
            403
        );

        if ($file->status === SharedFile::STATUS_ACTIVE && $file->isExpiredByTime()) {
            $file->forceFill(['status' => SharedFile::STATUS_EXPIRED])->save();
            $file->refresh();
        }

        if ($file->isSecurityScanPending()) {
            return back()->withErrors(['download' => __('messages.download.security_pending')]);
        }

        if (! $file->isSecurityApproved()) {
            return back()->withErrors(['download' => __('messages.download.security_rejected')]);
        }

        if ($file->status === SharedFile::STATUS_DELETED) {
            return back()->withErrors(['download' => __('messages.download.deleted')]);
        }

        if ($file->status === SharedFile::STATUS_EXPIRED || $file->isExpiredByTime()) {
            return back()->withErrors(['download' => __('messages.download.expired')]);
        }

        if ($file->status !== SharedFile::STATUS_ACTIVE) {
            return back()->withErrors(['download' => __('messages.download.inactive')]);
        }

        if ($file->isPasswordProtected() && ! $this->isUnlocked($request, $file)) {
            return redirect()->route('file-sends.unlock', $fileSend);
        }

        if (! Storage::exists($file->storage_path)) {
            return back()->withErrors(['download' => __('messages.download.file_missing')]);
        }

        if ($fileSend->receiver_id === $userId && ! $fileSend->downloaded_at) {
            $fileSend->forceFill(['downloaded_at' => now()])->save();
        }

        return Storage::download($file->storage_path, $file->original_name);
    }

    private function isUnlocked(Request $request, SharedFile $file): bool
    {
        return (bool) $request->session()->get('download_password_unlocked_'.$file->id, false);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\Setting;
use App\Models\SharedFile;
use App\Support\FilePreviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicFileDownloadController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $fileSend = $this->findByToken($token);
        abort_unless($fileSend !== null, 404);

        $file = $fileSend->file;
        $error = $this->validateAccess($fileSend, $file, false);
        $isUnlocked = $this->isUnlocked($request, $fileSend);
        $needsPassword = $file->isPasswordProtected() && ! $isUnlocked;
        $previewPolicy = FilePreviewPolicy::fromSettings();
        $previewType = FilePreviewPolicy::detectType($file, $previewPolicy);
        $canPreview = $error === null
            && ! $needsPassword
            && FilePreviewPolicy::canPreview($file, $previewPolicy);

        return view('public.download', [
            'fileSend' => $fileSend,
            'file' => $file,
            'needsPassword' => $needsPassword,
            'errorText' => $error,
            'canDownload' => $error === null && ! $needsPassword,
            'previewType' => $previewType,
            'canPreview' => $canPreview,
        ]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $fileSend = $this->findByToken($token);
        abort_unless($fileSend !== null, 404);

        $file = $fileSend->file;
        $error = $this->validateAccess($fileSend, $file);

        if ($error !== null) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => $error]);
        }

        if (! $file->isPasswordProtected()) {
            return redirect()->route('public-files.download', $token);
        }

        $validated = $request->validate([
            'download_password' => ['required', 'string', 'max:120'],
        ]);

        if (! Hash::check($validated['download_password'], (string) $file->download_password_hash)) {
            return back()->withErrors([
                'download_password' => __('messages.public_download.password_incorrect'),
            ]);
        }

        $request->session()->put($this->sessionKey($fileSend), true);

        return redirect()
            ->route('public-files.download', $token)
            ->with('status', __('messages.public_download.password_correct'));
    }

    public function download(Request $request, string $token): StreamedResponse|RedirectResponse
    {
        $fileSend = $this->findByToken($token);
        abort_unless($fileSend !== null, 404);

        $file = $fileSend->file;
        $error = $this->validateAccess($fileSend, $file);

        if ($error !== null) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => $error]);
        }

        if ($file->isPasswordProtected() && ! $this->isUnlocked($request, $fileSend)) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => __('messages.public_download.enter_password_download')]);
        }

        $freshSend = $fileSend->fresh();
        $freshFile = $file->fresh();

        if (! $freshSend || ! $freshFile) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => __('messages.public_download.record_missing')]);
        }

        $error = $this->validateAccess($freshSend, $freshFile);

        if ($error !== null) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => $error]);
        }

        $freshSend->forceFill([
            'public_download_count' => ((int) $freshSend->public_download_count) + 1,
            'public_last_downloaded_at' => now(),
            'downloaded_at' => $freshSend->downloaded_at ?: now(),
        ])->save();

        DB::table('file_send_public_downloads')->insert([
            'file_send_id' => $freshSend->id,
            'ip_address' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Storage::download($freshFile->storage_path, $freshFile->original_name);
    }

    public function preview(Request $request, string $token): StreamedResponse|RedirectResponse
    {
        $fileSend = $this->findByToken($token);
        abort_unless($fileSend !== null, 404);

        $file = $fileSend->file;
        $error = $this->validateAccess($fileSend, $file);

        if ($error !== null) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => $error]);
        }

        if ($file->isPasswordProtected() && ! $this->isUnlocked($request, $fileSend)) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => __('messages.public_download.enter_password_preview')]);
        }

        $previewPolicy = FilePreviewPolicy::fromSettings();

        if (! FilePreviewPolicy::canPreview($file, $previewPolicy)) {
            return redirect()
                ->route('public-files.download', $token)
                ->withErrors(['public_download' => __('messages.public_download.preview_disabled')]);
        }

        return Storage::response(
            $file->storage_path,
            $file->original_name,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream'],
            'inline'
        );
    }

    private function findByToken(string $token): ?FileSend
    {
        return FileSend::query()
            ->with(['file', 'sender'])
            ->where('public_token', $token)
            ->first();
    }

    private function validateAccess(FileSend $fileSend, SharedFile $file, bool $checkSecurity = true): ?string
    {
        if (Setting::getValue('public_link_enabled', 'false') !== 'true') {
            return __('messages.public_download.disabled_by_admin');
        }

        if (! $fileSend->public_link_enabled) {
            return __('messages.public_download.disabled_by_sender');
        }

        if ($file->status === SharedFile::STATUS_ACTIVE && $file->isExpiredByTime()) {
            $file->forceFill(['status' => SharedFile::STATUS_EXPIRED])->save();
            $file->refresh();
        }

        if ($checkSecurity && $file->isSecurityScanPending()) {
            return __('messages.public_download.security_pending');
        }

        if ($checkSecurity && ! $file->isSecurityApproved()) {
            return __('messages.public_download.security_rejected');
        }

        if ($checkSecurity && ! $file->isDownloadable()) {
            return __('messages.public_download.not_downloadable');
        }

        if (! $checkSecurity && ! $file->isPreviewableFileAvailable()) {
            return __('messages.public_download.not_downloadable');
        }

        if ($fileSend->isPublicLinkExpired()) {
            return __('messages.public_download.link_expired');
        }

        if ($fileSend->hasPublicDownloadLimitReached()) {
            return __('messages.public_download.download_limit_reached');
        }

        if (! Storage::exists($file->storage_path)) {
            return __('messages.public_download.file_missing');
        }

        return null;
    }

    private function isUnlocked(Request $request, FileSend $fileSend): bool
    {
        return (bool) $request->session()->get($this->sessionKey($fileSend), false);
    }

    private function sessionKey(FileSend $fileSend): string
    {
        return 'public_download_unlocked_'.$fileSend->id;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SharedFile;
use App\Support\FilePreviewPolicy;
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonalStorageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('storage.index', [
            'planProfile' => PlanPolicy::profileForUser($user),
            'storageProfile' => PersonalStorageQuota::profileForUser($user),
            'previewPolicy' => FilePreviewPolicy::fromSettings(),
            'files' => SharedFile::query()
                ->where('owner_id', $user->id)
                ->where('is_personal_storage', true)
                ->where('status', '!=', SharedFile::STATUS_DELETED)
                ->latest()
                ->get(),
        ]);
    }

    public function preview(Request $request, SharedFile $file): StreamedResponse|RedirectResponse
    {
        $this->ensureOwnedStorageFile($request, $file);

        if ($file->isSecurityScanPending()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_pending')]);
        }

        if (! $file->isSecurityApproved()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_security')]);
        }

        if (! $file->isDownloadable()) {
            return back()->withErrors(['preview' => __('ui.errors.preview_unavailable')]);
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

    public function download(Request $request, SharedFile $file): StreamedResponse|RedirectResponse
    {
        $this->ensureOwnedStorageFile($request, $file);

        if ($file->isSecurityScanPending()) {
            return back()->withErrors(['download' => __('messages.download.security_pending')]);
        }

        if (! $file->isSecurityApproved()) {
            return back()->withErrors(['download' => __('messages.download.security_rejected')]);
        }

        if ($file->status === SharedFile::STATUS_DELETED) {
            return back()->withErrors(['download' => __('messages.download.deleted')]);
        }

        if ($file->status !== SharedFile::STATUS_ACTIVE) {
            return back()->withErrors(['download' => __('messages.download.inactive')]);
        }

        if (! Storage::exists($file->storage_path)) {
            return back()->withErrors(['download' => __('messages.download.file_missing')]);
        }

        return Storage::download($file->storage_path, $file->original_name);
    }

    public function destroy(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureOwnedStorageFile($request, $file);

        if ($file->status !== SharedFile::STATUS_DELETED) {
            if ($file->storage_path !== '' && Storage::exists($file->storage_path)) {
                Storage::delete($file->storage_path);
            }

            $file->forceFill([
                'status' => SharedFile::STATUS_DELETED,
            ])->save();
        }

        return back()->with('status', __('messages.personal_storage.deleted'));
    }

    private function ensureOwnedStorageFile(Request $request, SharedFile $file): void
    {
        abort_unless(
            $file->owner_id === $request->user()->id && $file->is_personal_storage,
            403
        );
    }
}

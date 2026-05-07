<?php

namespace App\Http\Controllers;

use App\Models\FileStorageAccess;
use App\Models\SharedFile;
use App\Models\StorageFolder;
use App\Models\User;
use App\Support\FileTypeCatalog;
use App\Support\FilePreviewPolicy;
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonalStorageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $previewPolicy = FilePreviewPolicy::fromSettings();
        $search = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', 'all'));
        $sender = trim((string) $request->query('sender', ''));
        $recipient = trim((string) $request->query('recipient', ''));
        $contact = trim((string) $request->query('contact', ''));
        $scope = trim((string) $request->query('scope', 'all'));
        $folder = trim((string) $request->query('folder', 'all'));
        $period = trim((string) $request->query('period', 'all'));
        $view = in_array((string) $request->query('view', 'list'), ['list', 'grid'], true)
            ? (string) $request->query('view', 'list')
            : 'list';
        $thumbSize = in_array((string) $request->query('thumb', 'md'), ['sm', 'md', 'lg', 'xl'], true)
            ? (string) $request->query('thumb', 'md')
            : 'md';
        $workspaceAccessTableReady = Schema::hasTable('file_storage_access');
        $foldersTableReady = Schema::hasTable('storage_folders');
        $folderFeaturesEnabled = $foldersTableReady
            && $workspaceAccessTableReady
            && (bool) (PlanPolicy::profileForUser($user)['allow_folders'] ?? false);
        $storageFolders = $folderFeaturesEnabled
            ? StorageFolder::query()
                ->where('owner_id', $user->id)
                ->orderByRaw('COALESCE(parent_id, 0)')
                ->orderBy('name')
                ->get(['id', 'owner_id', 'parent_id', 'name'])
            : collect();
        $folderOptions = $folderFeaturesEnabled
            ? $this->folderOptionsForSelect($storageFolders)
            : [];

        $filesQuery = SharedFile::query()
            ->with(['sends.sender', 'sends.receiver'])
            ->where('is_personal_storage', true)
            ->where('status', '!=', SharedFile::STATUS_DELETED)
            ->latest();

        if ($workspaceAccessTableReady) {
            $filesQuery
                ->with([
                    'workspaceAccesses' => fn ($query) => $query->where('user_id', $user->id),
                ])
                ->where(function ($query) use ($user): void {
                    $query
                        ->where('owner_id', $user->id)
                        ->orWhereHas('workspaceAccesses', fn ($accessQuery) => $accessQuery->where('user_id', $user->id));
                });
        } else {
            $filesQuery->where('owner_id', $user->id);
        }

        $files = $filesQuery
            ->get()
            ->map(function (SharedFile $file) use ($previewPolicy, $user): SharedFile {
                $isTextNote = $this->isInlineTextNote($file);
                $originSend = $file->sends
                    ->sortByDesc(fn ($send) => $send->created_at?->getTimestamp() ?? 0)
                    ->first();
                $originSender = $originSend?->sender;
                $workspaceAccess = $file->workspaceAccessFor($user);
                $recipientUsers = $file->sends
                    ->map(fn ($send) => $send->receiver)
                    ->filter()
                    ->unique('id')
                    ->values();
                $previewType = $isTextNote ? 'note' : FilePreviewPolicy::detectType($file, $previewPolicy);
                $thumbnailUrl = $previewType === 'image' && FilePreviewPolicy::canPreview($file, $previewPolicy)
                    ? route('storage.preview', $file)
                    : null;

                $file->setAttribute('is_text_note', $isTextNote);
                $file->setAttribute('can_inline_preview', $isTextNote || FilePreviewPolicy::canPreview($file, $previewPolicy));
                $file->setAttribute('note_excerpt', $isTextNote ? $this->readNoteExcerpt($file) : null);
                $file->setAttribute('storage_category', $isTextNote ? 'note' : $file->category());
                $file->setAttribute('origin_sender_name', $originSender?->full_name ?: $originSender?->username);
                $file->setAttribute('origin_sender_username', $originSender?->username);
                $file->setAttribute('recipient_names', $recipientUsers->map(fn ($recipient) => $recipient->full_name ?: $recipient->username)->all());
                $file->setAttribute('recipient_usernames', $recipientUsers->map(fn ($recipient) => $recipient->username)->all());
                $file->setAttribute('recipient_summary', $recipientUsers->map(fn ($recipient) => $recipient->full_name ?: $recipient->username)->implode('، '));
                $file->setAttribute('preview_type', $previewType);
                $file->setAttribute('thumbnail_url', $thumbnailUrl);
                $file->setAttribute('thumbnail_icon', $this->thumbnailGlyphFor($file, $previewType));
                $file->setAttribute('thumbnail_extension', strtoupper((string) ($file->extension ?: __('ui.file_types.generic_short'))));
                $file->setAttribute('workspace_context', (string) ($workspaceAccess?->context ?: FileStorageAccess::CONTEXT_OWNED));
                $file->setAttribute('workspace_role', (string) ($workspaceAccess?->role ?: FileStorageAccess::ROLE_OWNER));
                $file->setAttribute('is_workspace_owner', $file->owner_id === $user->id);
                $file->setAttribute('can_manage_workspace', $file->canUserManageStorage($user));
                $file->setAttribute('workspace_folder_id', $workspaceAccess?->folder_id);
                $file->setAttribute('workspace_folder_name', $workspaceAccess?->folder?->name);

                return $file;
            });

        $filteredFiles = $this->applyStorageFilters($files, [
            'search' => $search,
            'type' => $type,
            'sender' => $sender,
            'recipient' => $recipient,
            'contact' => $contact,
            'scope' => $scope,
            'folder' => $folder,
            'period' => $period,
        ]);
        $availableSenders = $files
            ->filter(fn (SharedFile $file) => filled($file->getAttribute('origin_sender_username')))
            ->mapWithKeys(fn (SharedFile $file) => [
                (string) $file->getAttribute('origin_sender_username') => (string) ($file->getAttribute('origin_sender_name') ?: $file->getAttribute('origin_sender_username')),
            ])
            ->sortKeys()
            ->all();
        $availableRecipients = $files
            ->flatMap(function (SharedFile $file): array {
                $usernames = (array) $file->getAttribute('recipient_usernames');
                $names = (array) $file->getAttribute('recipient_names');
                $items = [];

                foreach ($usernames as $index => $username) {
                    if (! filled($username)) {
                        continue;
                    }

                    $items[$username] = (string) ($names[$index] ?? $username);
                }

                return $items;
            })
            ->sortKeys()
            ->all();
        $folderTree = $folderFeaturesEnabled
            ? $this->buildFolderTree($storageFolders, $files, $folder)
            : [];

        return view('storage.index', [
            'planProfile' => PlanPolicy::profileForUser($user),
            'storageProfile' => PersonalStorageQuota::profileForUser($user),
            'previewPolicy' => $previewPolicy,
            'files' => $filteredFiles,
            'viewMode' => $view,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'sender' => $sender,
                'recipient' => $recipient,
                'contact' => $contact,
                'scope' => $scope,
                'folder' => $folder,
                'period' => $period,
            ],
            'availableSenders' => $availableSenders,
            'availableRecipients' => $availableRecipients,
            'thumbnailSize' => $thumbSize,
            'folderFeaturesEnabled' => $folderFeaturesEnabled,
            'folderOptions' => $folderOptions,
            'folderTree' => $folderTree,
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureFolderFeaturesEnabled($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:storage_folders,id'],
        ]);

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId) {
            StorageFolder::query()
                ->where('owner_id', $user->id)
                ->findOrFail($parentId);
        }

        StorageFolder::query()->create([
            'owner_id' => $user->id,
            'parent_id' => $parentId,
            'name' => trim((string) $validated['name']),
        ]);

        return back()->with('status', __('messages.personal_storage.folder_created'));
    }

    public function updateFolder(Request $request, StorageFolder $folder): RedirectResponse
    {
        $user = $request->user();
        $this->ensureFolderFeaturesEnabled($user);
        abort_unless((int) $folder->owner_id === (int) $user->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->forceFill([
            'name' => trim((string) $validated['name']),
        ])->save();

        return back()->with('status', __('messages.personal_storage.folder_updated'));
    }

    public function destroyFolder(Request $request, StorageFolder $folder): RedirectResponse
    {
        $user = $request->user();
        $this->ensureFolderFeaturesEnabled($user);
        abort_unless((int) $folder->owner_id === (int) $user->id, 403);

        $hasChildren = StorageFolder::query()
            ->where('parent_id', $folder->id)
            ->exists();
        $hasFiles = FileStorageAccess::query()
            ->where('user_id', $user->id)
            ->where('folder_id', $folder->id)
            ->exists();

        if ($hasChildren || $hasFiles) {
            return back()->withErrors(['folder' => __('messages.personal_storage.folder_not_empty')]);
        }

        $folder->delete();

        return back()->with('status', __('messages.personal_storage.folder_deleted'));
    }

    public function moveToFolder(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureManageableStorageFile($request, $file);
        abort_unless(Schema::hasTable('storage_folders') && Schema::hasTable('file_storage_access'), 403);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:storage_folders,id'],
        ]);

        $user = $request->user();
        $folderId = $validated['folder_id'] ?? null;

        if ($folderId) {
            StorageFolder::query()
                ->where('owner_id', $user->id)
                ->findOrFail($folderId);
        }

        $workspaceAccess = $file->workspaceAccessFor($user);
        abort_unless($workspaceAccess, 403);

        $workspaceAccess->forceFill([
            'folder_id' => $folderId,
        ])->save();

        return back()->with('status', __('messages.personal_storage.moved_to_folder'));
    }

    public function preview(Request $request, SharedFile $file): StreamedResponse|RedirectResponse
    {
        $this->ensureAccessibleStorageFile($request, $file);

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

        if (! $this->isInlineTextNote($file) && ! FilePreviewPolicy::canPreview($file, $policy)) {
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
        $this->ensureAccessibleStorageFile($request, $file);

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
        $this->ensureManageableStorageFile($request, $file);
        $user = $request->user();

        if ($file->owner_id !== $user->id) {
            FileStorageAccess::query()
                ->where('file_id', $file->id)
                ->where('user_id', $user->id)
                ->delete();

            return back()->with('status', __('messages.personal_storage.removed_from_workspace'));
        }

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

    private function ensureAccessibleStorageFile(Request $request, SharedFile $file): void
    {
        abort_unless(
            $file->is_personal_storage && $file->canUserAccessStorage($request->user()),
            403
        );
    }

    private function ensureManageableStorageFile(Request $request, SharedFile $file): void
    {
        abort_unless(
            $file->is_personal_storage && $file->canUserManageStorage($request->user()),
            403
        );
    }

    private function ensureFolderFeaturesEnabled(User $user): void
    {
        $planProfile = PlanPolicy::profileForUser($user);

        abort_unless(
            Schema::hasTable('storage_folders')
            && Schema::hasTable('file_storage_access')
            && (bool) ($planProfile['allow_folders'] ?? false),
            403
        );
    }

    private function isInlineTextNote(SharedFile $file): bool
    {
        return $file->mime_type === 'text/plain'
            && $file->extension === 'txt'
            && $file->size <= 256 * 1024;
    }

    private function readNoteExcerpt(SharedFile $file): ?string
    {
        if (! $this->isInlineTextNote($file) || ! Storage::exists($file->storage_path)) {
            return null;
        }

        return Str::limit(trim((string) Storage::get($file->storage_path)), 220);
    }

    private function applyStorageFilters(Collection $files, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $type = trim((string) ($filters['type'] ?? 'all'));
        $types = collect(explode(',', $type))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->reject(fn (string $value): bool => $value === 'all')
            ->unique()
            ->values()
            ->all();
        $sender = trim((string) ($filters['sender'] ?? ''));
        $recipient = trim((string) ($filters['recipient'] ?? ''));
        $contact = trim((string) ($filters['contact'] ?? ''));
        $scope = trim((string) ($filters['scope'] ?? 'all'));
        $folder = trim((string) ($filters['folder'] ?? 'all'));
        $period = trim((string) ($filters['period'] ?? 'all'));
        $fromDate = $this->resolvePeriodStart($period);
        $allowedScopes = [
            'all',
            FileStorageAccess::CONTEXT_OWNED,
            FileStorageAccess::CONTEXT_SENT,
            FileStorageAccess::CONTEXT_RECEIVED,
        ];
        $normalizedScope = in_array($scope, $allowedScopes, true) ? $scope : 'all';

        return $files
            ->filter(function (SharedFile $file) use ($search, $types, $sender, $recipient, $contact, $folder, $fromDate): bool {
                if ($types !== [] && ! in_array((string) $file->getAttribute('storage_category'), $types, true)) {
                    return false;
                }

                if ($sender !== '' && (string) $file->getAttribute('origin_sender_username') !== $sender) {
                    return false;
                }

                if ($recipient !== '' && ! in_array($recipient, (array) $file->getAttribute('recipient_usernames'), true)) {
                    return false;
                }

                if (
                    $contact !== ''
                    && (string) $file->getAttribute('origin_sender_username') !== $contact
                    && ! in_array($contact, (array) $file->getAttribute('recipient_usernames'), true)
                ) {
                    return false;
                }

                if ($folder !== '' && $folder !== 'all') {
                    $folderId = $file->getAttribute('workspace_folder_id');

                    if ($folder === 'root') {
                        if ($folderId !== null) {
                            return false;
                        }
                    } elseif ((string) $folderId !== $folder) {
                        return false;
                    }
                }

                if ($fromDate && $file->created_at && $file->created_at->lt($fromDate)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystacks = [
                    mb_strtolower((string) $file->original_name),
                    mb_strtolower((string) $file->extension),
                    mb_strtolower((string) ($file->mime_type ?? '')),
                    mb_strtolower((string) ($file->getAttribute('note_excerpt') ?? '')),
                    mb_strtolower((string) ($file->getAttribute('origin_sender_name') ?? '')),
                    mb_strtolower((string) ($file->getAttribute('origin_sender_username') ?? '')),
                    mb_strtolower((string) ($file->getAttribute('recipient_summary') ?? '')),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains($haystack, $search)) {
                        return true;
                    }
                }

                return false;
            })
            ->filter(function (SharedFile $file) use ($normalizedScope): bool {
                if ($normalizedScope === 'all') {
                    return true;
                }

                return (string) $file->getAttribute('workspace_context') === $normalizedScope;
            })
            ->values();
    }

    private function resolvePeriodStart(string $period): ?Carbon
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => null,
        };
    }

    private function thumbnailGlyphFor(SharedFile $file, string $previewType): string
    {
        if ($previewType === 'note') {
            return '≣';
        }

        return match ($file->category()) {
            'image' => '▣',
            'video' => '▶',
            'document' => '≣',
            'archive' => '⌁',
            default => '•',
        };
    }
    private function folderOptionsForSelect(Collection $folders, ?int $parentId = null, int $depth = 0): array
    {
        $items = [];

        foreach ($folders->where('parent_id', $parentId)->sortBy('name') as $folder) {
            $items[$folder->id] = str_repeat('-- ', $depth).$folder->name;
            $items += $this->folderOptionsForSelect($folders, $folder->id, $depth + 1);
        }

        return $items;
    }

    private function buildFolderTree(Collection $folders, Collection $files, string $activeFolder, ?int $parentId = null): array
    {
        return $folders
            ->where('parent_id', $parentId)
            ->sortBy('name')
            ->map(function (StorageFolder $folder) use ($folders, $files, $activeFolder): array {
                $directCount = $files
                    ->filter(fn (SharedFile $file) => (int) ($file->getAttribute('workspace_folder_id') ?? 0) === $folder->id)
                    ->count();
                $children = $this->buildFolderTree($folders, $files, $activeFolder, $folder->id);

                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'count' => $directCount,
                    'active' => (string) $folder->id === $activeFolder,
                    'children' => $children,
                ];
            })
            ->values()
            ->all();
    }
}

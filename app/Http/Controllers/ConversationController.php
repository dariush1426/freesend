<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FileStorageAccess;
use App\Models\FileSend;
use App\Models\SharedFile;
use App\Models\Setting;
use App\Models\User;
use App\Support\FilePreviewPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function show(Request $request, User $user): View
    {
        abort_if($user->id === Auth::id(), 404);

        $currentUserId = Auth::id();
        $search = trim((string) $request->query('q'));
        $currentUser = $request->user();
        $previewPolicy = FilePreviewPolicy::fromSettings();

        $messages = FileSend::query()
            ->with(['file', 'sender', 'receiver'])
            ->where(function ($builder) use ($currentUserId, $user): void {
                $builder
                    ->where('sender_id', $currentUserId)
                    ->where('receiver_id', $user->id);
            })
            ->orWhere(function ($builder) use ($currentUserId, $user): void {
                $builder
                    ->where('sender_id', $user->id)
                    ->where('receiver_id', $currentUserId);
            })
            ->oldest()
            ->get();

        FileSend::query()
            ->where('sender_id', $user->id)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AppNotification::query()
            ->where('user_id', $currentUserId)
            ->where('type', 'file_received')
            ->whereNull('read_at')
            ->get()
            ->filter(fn (AppNotification $notification) => (int) ($notification->payload['sender_id'] ?? 0) === $user->id)
            ->each(fn (AppNotification $notification) => $notification->forceFill(['read_at' => now()])->save());

        $threads = $this->relatedThreads($currentUserId, $search, true);

        return view('conversations.show', [
            'conversationMode' => 'user',
            'otherUser' => $user,
            'messages' => $messages,
            'threads' => $threads,
            'search' => $search,
            'previewPolicy' => $previewPolicy,
            'workspaceFiles' => $this->workspaceFilesForExchange($currentUser, $user, $previewPolicy),
            'unlockedFileIds' => $messages
                ->map(fn (FileSend $message) => $message->file_id)
                ->filter(fn (int $fileId) => (bool) $request->session()->get('download_password_unlocked_'.$fileId, false))
                ->values()
                ->all(),
            'totalUnreadThreads' => $threads->where('unread', '>', 0)->count(),
            'totalUnreadNotifications' => AppNotification::query()
                ->where('user_id', $currentUserId)
                ->whereNull('read_at')
                ->count(),
            'publicLinkFeatureEnabled' => Setting::getValue('public_link_enabled', 'false') === 'true',
        ]);
    }

    public function storage(Request $request): View
    {
        $currentUser = $request->user();
        $currentUserId = (int) $currentUser->id;
        $search = trim((string) $request->query('q'));
        $previewPolicy = FilePreviewPolicy::fromSettings();

        $messages = FileSend::query()
            ->with(['file', 'sender', 'receiver'])
            ->where('sender_id', $currentUserId)
            ->where('receiver_id', $currentUserId)
            ->whereHas('file', fn ($query) => $query->where('is_personal_storage', true))
            ->oldest()
            ->get();

        AppNotification::query()
            ->where('user_id', $currentUserId)
            ->where('type', 'personal_storage_received')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('conversations.show', [
            'conversationMode' => 'storage',
            'otherUser' => null,
            'messages' => $messages,
            'threads' => $this->relatedThreads($currentUserId, $search, true),
            'search' => $search,
            'previewPolicy' => $previewPolicy,
            'workspaceFiles' => collect(),
            'unlockedFileIds' => [],
            'totalUnreadThreads' => 0,
            'totalUnreadNotifications' => AppNotification::query()
                ->where('user_id', $currentUserId)
                ->whereNull('read_at')
                ->count(),
            'publicLinkFeatureEnabled' => Setting::getValue('public_link_enabled', 'false') === 'true',
        ]);
    }

    private function workspaceFilesForExchange(User $currentUser, User $otherUser, array $previewPolicy): Collection
    {
        if (! Schema::hasTable('file_storage_access')) {
            return collect();
        }

        return SharedFile::query()
            ->with([
                'workspaceAccesses' => fn ($query) => $query
                    ->where('user_id', $currentUser->id)
                    ->with('folder'),
                'sends.sender',
                'sends.receiver',
            ])
            ->where('is_personal_storage', true)
            ->where('status', '!=', SharedFile::STATUS_DELETED)
            ->where(function ($query) use ($currentUser): void {
                $query
                    ->where('owner_id', $currentUser->id)
                    ->orWhereHas('workspaceAccesses', fn ($accessQuery) => $accessQuery->where('user_id', $currentUser->id));
            })
            ->whereHas('sends', function ($query) use ($currentUser, $otherUser): void {
                $query
                    ->where(function ($builder) use ($currentUser, $otherUser): void {
                        $builder
                            ->where('sender_id', $currentUser->id)
                            ->where('receiver_id', $otherUser->id);
                    })
                    ->orWhere(function ($builder) use ($currentUser, $otherUser): void {
                        $builder
                            ->where('sender_id', $otherUser->id)
                            ->where('receiver_id', $currentUser->id);
                    });
            })
            ->latest()
            ->get()
            ->map(function (SharedFile $file) use ($currentUser, $previewPolicy): SharedFile {
                $workspaceAccess = $file->workspaceAccessFor($currentUser);
                $previewType = $this->isInlineTextNote($file)
                    ? 'note'
                    : FilePreviewPolicy::detectType($file, $previewPolicy);
                $canPreview = $this->isInlineTextNote($file)
                    || FilePreviewPolicy::canPreview($file, $previewPolicy);

                $file->setAttribute('workspace_context', (string) ($workspaceAccess?->context ?: FileStorageAccess::CONTEXT_OWNED));
                $file->setAttribute('workspace_folder_name', $workspaceAccess?->folder?->name);
                $file->setAttribute('can_inline_preview', $canPreview);
                $file->setAttribute('note_excerpt', $this->isInlineTextNote($file) ? $this->readNoteExcerpt($file) : null);
                $file->setAttribute('preview_type', $previewType);

                return $file;
            })
            ->values();
    }

    private function relatedThreads(int $currentUserId, string $search, bool $includeStorageThread = false): Collection
    {
        $userIds = FileSend::query()
            ->where('sender_id', $currentUserId)
            ->orWhere('receiver_id', $currentUserId)
            ->latest()
            ->get()
            ->map(fn (FileSend $send) => $send->sender_id === $currentUserId ? $send->receiver_id : $send->sender_id)
            ->filter(fn ($otherId) => $otherId !== null && (int) $otherId !== $currentUserId)
            ->map(fn ($otherId) => (int) $otherId)
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $threads = $userIds->map(function (int $otherId) use ($currentUserId, $users) {
            $latestSend = FileSend::query()
                ->with(['file'])
                ->where(function ($builder) use ($currentUserId, $otherId): void {
                    $builder->where('sender_id', $currentUserId)->where('receiver_id', $otherId);
                })
                ->orWhere(function ($builder) use ($currentUserId, $otherId): void {
                    $builder->where('sender_id', $otherId)->where('receiver_id', $currentUserId);
                })
                ->latest()
                ->first();

            return [
                'user' => $users->get($otherId),
                'latest' => $latestSend,
                'unread' => FileSend::query()
                    ->where('sender_id', $otherId)
                    ->where('receiver_id', $currentUserId)
                    ->whereNull('read_at')
                    ->count(),
            ];
        })->filter(fn (array $thread) => $thread['user'] !== null)->values();

        if ($includeStorageThread) {
            $storageThread = $this->personalStorageThread($currentUserId);
            if ($storageThread !== null) {
                $threads = collect([$storageThread])->merge($threads)->values();
            }
        }

        if ($search === '') {
            return $threads;
        }

        $search = mb_strtolower($search);

        return $threads->filter(function (array $thread) use ($search): bool {
            if (($thread['type'] ?? 'user') === 'storage') {
                return str_contains(mb_strtolower((string) ($thread['label'] ?? '')), $search);
            }

            $user = $thread['user'];

            return str_contains(mb_strtolower((string) $user->username), $search)
                || str_contains(mb_strtolower((string) $user->full_name), $search)
                || str_contains(mb_strtolower((string) $user->email), $search)
                || str_contains(mb_strtolower((string) $user->mobile), $search);
        })->values();
    }

    private function personalStorageThread(int $currentUserId): ?array
    {
        $latestSend = FileSend::query()
            ->with('file')
            ->where('sender_id', $currentUserId)
            ->where('receiver_id', $currentUserId)
            ->whereHas('file', fn ($query) => $query->where('is_personal_storage', true))
            ->latest()
            ->first();

        if (! $latestSend) {
            return null;
        }

        $label = app()->getLocale() === 'fa' ? 'فضای شخصی' : 'Personal storage';

        return [
            'type' => 'storage',
            'label' => $label,
            'user' => null,
            'latest' => $latestSend,
            'unread' => AppNotification::query()
                ->where('user_id', $currentUserId)
                ->where('type', 'personal_storage_received')
                ->whereNull('read_at')
                ->count(),
        ];
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

        return Str::limit(trim((string) Storage::get($file->storage_path)), 160);
    }
}

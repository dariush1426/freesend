<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FileSend;
use App\Models\Setting;
use App\Models\User;
use App\Support\FilePreviewPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function show(Request $request, User $user): View
    {
        abort_if($user->id === Auth::id(), 404);

        $currentUserId = Auth::id();
        $search = trim((string) $request->query('q'));

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

        $threads = $this->relatedThreads($currentUserId, $search);

        return view('conversations.show', [
            'otherUser' => $user,
            'messages' => $messages,
            'threads' => $threads,
            'search' => $search,
            'previewPolicy' => FilePreviewPolicy::fromSettings(),
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

    private function relatedThreads(int $currentUserId, string $search): Collection
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

        if ($search === '') {
            return $threads;
        }

        $search = mb_strtolower($search);

        return $threads->filter(function (array $thread) use ($search): bool {
            $user = $thread['user'];

            return str_contains(mb_strtolower((string) $user->username), $search)
                || str_contains(mb_strtolower((string) $user->full_name), $search)
                || str_contains(mb_strtolower((string) $user->email), $search)
                || str_contains(mb_strtolower((string) $user->mobile), $search);
        })->values();
    }
}

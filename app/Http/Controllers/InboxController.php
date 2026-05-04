<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FileSend;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = Auth::id();
        $search = trim((string) $request->query('q'));

        $userIds = FileSend::query()
            ->where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->latest()
            ->get()
            ->map(fn (FileSend $send) => $send->sender_id === $userId ? $send->receiver_id : $send->sender_id)
            ->filter(fn ($otherId) => $otherId !== null && (int) $otherId !== $userId)
            ->map(fn ($otherId) => (int) $otherId)
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $threads = $userIds
            ->map(function (int $otherId) use ($userId, $users) {
                $latestSend = FileSend::query()
                    ->with(['file', 'sender', 'receiver'])
                    ->where(function ($builder) use ($userId, $otherId): void {
                        $builder
                            ->where('sender_id', $userId)
                            ->where('receiver_id', $otherId);
                    })
                    ->orWhere(function ($builder) use ($userId, $otherId): void {
                        $builder
                            ->where('sender_id', $otherId)
                            ->where('receiver_id', $userId);
                    })
                    ->latest()
                    ->first();

                return [
                    'user' => $users->get($otherId),
                    'latest' => $latestSend,
                    'unread' => FileSend::query()
                        ->where('sender_id', $otherId)
                        ->where('receiver_id', $userId)
                        ->whereNull('read_at')
                        ->count(),
                ];
            })
            ->filter(fn (array $thread) => $thread['user'] !== null)
            ->values();

        $filteredThreads = $this->filterThreads($threads, $search);
        $activeThread = $filteredThreads->first();

        return view('inbox.index', [
            'threads' => $filteredThreads,
            'search' => $search,
            'activeThread' => $activeThread,
            'totalUnreadThreads' => $filteredThreads->where('unread', '>', 0)->count(),
            'totalUnreadNotifications' => AppNotification::query()
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    private function filterThreads(Collection $threads, string $search): Collection
    {
        if ($search === '') {
            return $threads;
        }

        $search = mb_strtolower($search);

        return $threads
            ->filter(function (array $thread) use ($search): bool {
                $user = $thread['user'];

                return str_contains(mb_strtolower((string) $user->username), $search)
                    || str_contains(mb_strtolower((string) $user->full_name), $search)
                    || str_contains(mb_strtolower((string) $user->email), $search)
                    || str_contains(mb_strtolower((string) $user->mobile), $search);
            })
            ->values();
    }
}

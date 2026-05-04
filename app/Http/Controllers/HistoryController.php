<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\SharedFile;
use App\Support\FilePreviewPolicy;
use App\Support\FileTypeCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = Auth::id();
        $direction = (string) $request->query('direction', 'all');
        $status = (string) $request->query('status', 'all');
        $type = (string) $request->query('type', 'all');
        $search = trim((string) $request->query('q'));
        $counterparty = trim((string) $request->query('user'));

        $history = FileSend::query()
            ->with(['file', 'sender', 'receiver'])
            ->where(function (Builder $builder) use ($userId, $direction): void {
                if ($direction === 'sent') {
                    $builder->where('sender_id', $userId);

                    return;
                }

                if ($direction === 'received') {
                    $builder->where('receiver_id', $userId);

                    return;
                }

                $builder
                    ->where('sender_id', $userId)
                    ->orWhere('receiver_id', $userId);
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->whereHas('file', fn (Builder $q) => $q->where('original_name', 'like', "%{$search}%"))
                        ->orWhere('sender_name', 'like', "%{$search}%")
                        ->orWhere('sender_contact', 'like', "%{$search}%")
                        ->orWhereHas('sender', fn (Builder $q) => $q->where('username', 'like', "%{$search}%"))
                        ->orWhereHas('receiver', fn (Builder $q) => $q->where('username', 'like', "%{$search}%"));
                });
            })
            ->when($counterparty !== '', function (Builder $builder) use ($counterparty, $userId): void {
                $builder->where(function (Builder $nested) use ($counterparty, $userId): void {
                    $nested
                        ->where(function (Builder $guestSender) use ($counterparty): void {
                            $guestSender
                                ->whereNull('sender_id')
                                ->where(function (Builder $guestMatch) use ($counterparty): void {
                                    $guestMatch
                                        ->where('sender_name', 'like', "%{$counterparty}%")
                                        ->orWhere('sender_contact', 'like', "%{$counterparty}%");
                                });
                        })
                        ->orWhereHas('sender', function (Builder $q) use ($counterparty, $userId): void {
                            $q->where('id', '!=', $userId)
                                ->where(function (Builder $match) use ($counterparty): void {
                                    $match
                                        ->where('username', 'like', "%{$counterparty}%")
                                        ->orWhere('full_name', 'like', "%{$counterparty}%")
                                        ->orWhere('email', 'like', "%{$counterparty}%")
                                        ->orWhere('mobile', 'like', "%{$counterparty}%");
                                });
                        })
                        ->orWhereHas('receiver', function (Builder $q) use ($counterparty, $userId): void {
                            $q->where('id', '!=', $userId)
                                ->where(function (Builder $match) use ($counterparty): void {
                                    $match
                                        ->where('username', 'like', "%{$counterparty}%")
                                        ->orWhere('full_name', 'like', "%{$counterparty}%")
                                        ->orWhere('email', 'like', "%{$counterparty}%")
                                        ->orWhere('mobile', 'like', "%{$counterparty}%");
                                });
                        });
                });
            })
            ->when($type !== 'all', function (Builder $builder) use ($type): void {
                $rule = FileTypeCatalog::rule($type);

                $builder->whereHas('file', function (Builder $q) use ($rule): void {
                    $q->where(function (Builder $fileQuery) use ($rule): void {
                        if (! empty($rule['extensions'])) {
                            $fileQuery->whereIn('extension', $rule['extensions']);
                        }

                        foreach ($rule['mime_prefixes'] as $index => $prefix) {
                            $method = $index === 0 && empty($rule['extensions']) ? 'where' : 'orWhere';
                            $fileQuery->{$method}('mime_type', 'like', $prefix.'%');
                        }
                    });
                });
            })
            ->when($status !== 'all', function (Builder $builder) use ($status, $userId): void {
                match ($status) {
                    'read' => $builder->where('receiver_id', $userId)->whereNotNull('read_at'),
                    'unread' => $builder->where('receiver_id', $userId)->whereNull('read_at'),
                    'downloaded' => $builder->whereNotNull('downloaded_at'),
                    'not_downloaded' => $builder->whereNull('downloaded_at'),
                    'public' => $builder->where('public_link_enabled', true),
                    'active' => $builder->whereHas('file', function (Builder $q): void {
                        $q->where('status', SharedFile::STATUS_ACTIVE)
                            ->where(function (Builder $time): void {
                                $time->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    }),
                    'expired' => $builder->whereHas('file', function (Builder $q): void {
                        $q->where('status', SharedFile::STATUS_EXPIRED)
                            ->orWhere(function (Builder $time): void {
                                $time->whereNotNull('expires_at')->where('expires_at', '<=', now());
                            });
                    }),
                    'deleted' => $builder->whereHas('file', fn (Builder $q) => $q->where('status', SharedFile::STATUS_DELETED)),
                    default => null,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('history.index', [
            'history' => $history,
            'direction' => $direction,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'counterparty' => $counterparty,
            'previewPolicy' => FilePreviewPolicy::fromSettings(),
            'unlockedFileIds' => $history->getCollection()
                ->map(fn (FileSend $row) => $row->file_id)
                ->filter(fn (int $fileId) => (bool) $request->session()->get('download_password_unlocked_'.$fileId, false))
                ->values()
                ->all(),
            'fileTypeOptions' => FileTypeCatalog::categories(),
        ]);
    }
}

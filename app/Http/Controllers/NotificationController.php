<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        $senderId = (int) ($notification->payload['sender_id'] ?? 0);
        $fileSendId = (int) ($notification->payload['file_send_id'] ?? 0);
        $guestSender = (bool) ($notification->payload['guest_sender'] ?? false);

        if (
            $notification->type === 'file_received'
            && $guestSender
            && $fileSendId > 0
        ) {
            return redirect()->route('guest-file-sends.show', $fileSendId);
        }

        if (
            $notification->type === 'file_received'
            && $senderId > 0
            && $senderId !== $request->user()->id
            && User::query()->whereKey($senderId)->exists()
        ) {
            return redirect()->route('conversations.show', $senderId);
        }

        return redirect()->route('dashboard');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', __('ui.notifications.all_read'));
    }
}

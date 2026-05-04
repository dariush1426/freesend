<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FileSend;
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();

        return view('dashboard.index', [
            'planProfile' => PlanPolicy::profileForUser($user),
            'storageProfile' => PersonalStorageQuota::profileForUser($user),
            'receivedCount' => FileSend::query()->where('receiver_id', $user->id)->count(),
            'sentCount' => FileSend::query()->where('sender_id', $user->id)->count(),
            'unreadCount' => AppNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'notifications' => AppNotification::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}

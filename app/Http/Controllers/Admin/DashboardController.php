<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileSend;
use App\Models\SharedFile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::query()->count(),
            'adminUsers' => User::query()->where('is_admin', true)->count(),
            'totalTransfers' => FileSend::query()->count(),
            'storedFiles' => SharedFile::query()->count(),
            'verifiedEmails' => User::query()->whereNotNull('email_verified_at')->count(),
            'verifiedMobiles' => User::query()->whereNotNull('mobile_verified_at')->count(),
            'totalPlans' => SubscriptionPlan::query()->count(),
            'activeSubscriptions' => UserSubscription::query()
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->count(),
        ]);
    }
}

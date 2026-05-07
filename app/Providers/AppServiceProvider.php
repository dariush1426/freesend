<?php

namespace App\Providers;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $data = [
                'layoutUnreadNotifications' => 0,
                'layoutRecentNotifications' => collect(),
                'layoutNotificationsTotal' => 0,
            ];

            if (Auth::check()) {
                $userId = Auth::id();

                $data['layoutUnreadNotifications'] = AppNotification::query()
                    ->where('user_id', $userId)
                    ->whereNull('read_at')
                    ->count();

                $data['layoutRecentNotifications'] = AppNotification::query()
                    ->where('user_id', $userId)
                    ->latest()
                    ->limit(5)
                    ->get();

                $data['layoutNotificationsTotal'] = AppNotification::query()
                    ->where('user_id', $userId)
                    ->count();
            }

            $view->with($data);
        });
    }
}

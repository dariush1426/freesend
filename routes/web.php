<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SubscriptionOrderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileDownloadController;
use App\Http\Controllers\FileDownloadUnlockController;
use App\Http\Controllers\FilePreviewController;
use App\Http\Controllers\FileSendController;
use App\Http\Controllers\FileSendPublicLinkController;
use App\Http\Controllers\GuestFileSendController;
use App\Http\Controllers\GuestQuickSendController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalStorageController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileVerificationController;
use App\Http\Controllers\PublicFileDownloadController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\Admin\SubscriptionSubscriberController;
use App\Http\Controllers\UserLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/login/otp', [OtpAuthController::class, 'showLogin'])->name('otp.login');
    Route::post('/login/otp/request', [OtpAuthController::class, 'sendLoginOtp'])->name('otp.login.request');
    Route::post('/login/otp/verify', [OtpAuthController::class, 'verifyLoginOtp'])->name('otp.login.verify');

    Route::get('/register/otp', [OtpAuthController::class, 'showRegister'])->name('otp.register');
    Route::post('/register/otp/request', [OtpAuthController::class, 'sendRegisterOtp'])->name('otp.register.request');
    Route::post('/register/otp/verify', [OtpAuthController::class, 'verifyRegisterOtp'])->name('otp.register.verify');
    Route::post('/register/otp/complete', [OtpAuthController::class, 'completeRegister'])->name('otp.register.complete');

    Route::get('/quick-send', [GuestQuickSendController::class, 'create'])->name('quick-send.create');
    Route::post('/quick-send', [GuestQuickSendController::class, 'store'])->name('quick-send.store');
});

Route::get('/p/{token}', [PublicFileDownloadController::class, 'show'])->name('public-files.download');
Route::post('/p/{token}', [PublicFileDownloadController::class, 'verify'])->name('public-files.download.verify');
Route::get('/p/{token}/file', [PublicFileDownloadController::class, 'download'])->name('public-files.download.file');
Route::get('/p/{token}/preview', [PublicFileDownloadController::class, 'preview'])->name('public-files.preview');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/pwa/logo/{variant}', [PwaController::class, 'logo'])->name('pwa.logo');
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/subscriptions/payments/zibal/callback', [SubscriptionPaymentController::class, 'zibalCallback'])
    ->name('subscriptions.payments.zibal.callback');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/subscriptions/upgrade', [SubscriptionController::class, 'index'])->name('subscriptions.upgrade');
    Route::post('/subscriptions/{plan}/purchase', [SubscriptionController::class, 'purchase'])->name('subscriptions.purchase');
    Route::get('/storage', [PersonalStorageController::class, 'index'])->name('storage.index');
    Route::get('/storage/{file}/preview', [PersonalStorageController::class, 'preview'])->name('storage.preview');
    Route::get('/storage/{file}/download', [PersonalStorageController::class, 'download'])->name('storage.download');
    Route::delete('/storage/{file}', [PersonalStorageController::class, 'destroy'])->name('storage.destroy');
    Route::get('/send', [FileSendController::class, 'create'])->name('files.create');
    Route::post('/send', [FileSendController::class, 'store'])->name('files.store');
    Route::post('/uploads/chunk/start', [ChunkUploadController::class, 'start'])->name('uploads.chunk.start');
    Route::post('/uploads/chunk/status', [ChunkUploadController::class, 'status'])->name('uploads.chunk.status');
    Route::post('/uploads/chunk/part', [ChunkUploadController::class, 'part'])
        ->middleware('chunk.bandwidth')
        ->name('uploads.chunk.part');
    Route::post('/uploads/chunk/finish', [ChunkUploadController::class, 'finish'])->name('uploads.chunk.finish');
    Route::get('/users/lookup', UserLookupController::class)->name('users.lookup');
    Route::get('/inbox', InboxController::class)->name('inbox');
    Route::get('/history', HistoryController::class)->name('history.index');
    Route::get('/conversations/{user}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/verify/email/request', [ProfileVerificationController::class, 'requestEmail'])->name('profile.verify.email.request');
    Route::post('/profile/verify/email/confirm', [ProfileVerificationController::class, 'verifyEmail'])->name('profile.verify.email.confirm');
    Route::post('/profile/verify/mobile/request', [ProfileVerificationController::class, 'requestMobile'])->name('profile.verify.mobile.request');
    Route::post('/profile/verify/mobile/confirm', [ProfileVerificationController::class, 'verifyMobile'])->name('profile.verify.mobile.confirm');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/file-sends/{fileSend}/unlock', [FileDownloadUnlockController::class, 'show'])->name('file-sends.unlock');
    Route::post('/file-sends/{fileSend}/unlock', [FileDownloadUnlockController::class, 'verify'])->name('file-sends.unlock.verify');
    Route::get('/file-sends/{fileSend}/preview', FilePreviewController::class)->name('file-sends.preview');
    Route::get('/file-sends/{fileSend}/download', FileDownloadController::class)->name('file-sends.download');
    Route::post('/file-sends/{fileSend}/public-link', [FileSendPublicLinkController::class, 'update'])->name('file-sends.public-link.update');
    Route::get('/guest-file-sends/{fileSend}', [GuestFileSendController::class, 'show'])->name('guest-file-sends.show');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [SubscriptionPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
        Route::patch('/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
        Route::get('/subscribers', [SubscriptionSubscriberController::class, 'index'])->name('subscribers.index');
        Route::post('/subscribers/assign', [SubscriptionSubscriberController::class, 'assign'])->name('subscribers.assign');
        Route::get('/orders', [SubscriptionOrderController::class, 'index'])->name('orders.index');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [SettingsController::class, 'sendTestEmail'])->name('settings.test-email');
        Route::post('/settings/test-sms', [SettingsController::class, 'sendTestSms'])->name('settings.test-sms');
    });
});

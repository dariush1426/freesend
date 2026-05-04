<?php

use App\Support\FileLifecycle;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('files:cleanup-expired {--dry-run : Only report what will be changed}', function () {
    $result = FileLifecycle::cleanupExpiredFiles((bool) $this->option('dry-run'));

    $this->info('Expired marked: '.$result['expired_marked']);
    $this->info('Cleanup candidates: '.$result['cleanup_candidates']);
    $this->info('Deleted from storage: '.$result['storage_deleted']);
    $this->info('Records marked deleted: '.$result['records_marked_deleted']);
    $this->line('Mode: '.($result['dry_run'] ? 'dry-run' : 'write'));
})->purpose('Mark expired files and clean them from storage');

Schedule::command('files:cleanup-expired')->hourly()->withoutOverlapping();

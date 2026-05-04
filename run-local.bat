@echo off
cd /d C:\wamp64\www\freesend

echo [1/5] Clearing caches...
php artisan optimize:clear

echo [2/5] Running migrations...
php artisan migrate --force

echo [3/5] Starting queue worker (new window)...
start "freesend-queue" cmd /k php artisan queue:work --tries=1

echo [4/5] Starting scheduler worker (new window)...
start "freesend-scheduler" cmd /k php artisan schedule:work

echo [5/5] Starting app server...
php artisan serve --host=127.0.0.1 --port=8000

# FreeSend

FreeSend is a Laravel monolith MVP for sending files between registered users.

## Phase 1 scope

- Register and login with username, email, or mobile.
- Unique username, email, and mobile.
- Search receiver by username, mobile, or email.
- Upload a small file and send it to one receiver.
- Internal notification for the receiver.
- Email notification through the configured Laravel mailer.
- Conversation-style file inbox.
- Controlled download for conversation participants.
- Simple admin settings for file size, allowed extensions, expiry, and feature flags.

Not included in phase 1: OTP/SMS, public links, encryption, previews, multi-recipient sends, and large/chunked uploads.

## Local run

```bash
composer install --no-dev
php artisan key:generate
php artisan migrate
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://localhost:8000
```

The first registered user becomes admin automatically.

## Database

The current local `.env` uses SQLite so the MVP can run immediately:

```text
DB_CONNECTION=sqlite
```

For WAMP MySQL/MariaDB, create a database named `freesend`, then update `.env`:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freesend
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate:fresh
```

## Storage

Uploaded files are stored under Laravel's private local disk:

```text
storage/app/private/files
```

They are not placed in `public`; downloads pass through Laravel authorization.

## Real email notifications

By default, local `.env` uses `MAIL_MAILER=log`, so emails are written to `storage/logs/laravel.log`.

To send real emails:

1. Login as admin and open `Settings`.
2. In `تنظیمات ایمیل واقعی (SMTP)`, set:
   - `نوع Mailer` to `SMTP` (or `Failover`)
   - SMTP host, port, username, password, encryption
   - From address/name
3. Save settings.
4. Use the `تست ایمیل` form on the same page.

For repeated local startup on Windows/WAMP, you can run:

```bat
run-local.bat
```

This script now starts:
- queue worker (`php artisan queue:work`)
- scheduler worker (`php artisan schedule:work`)

## Expiry and cleanup (Phase 2)

- Sender can choose file expiry when sending (`1h`, `2h`, `5h`, `12h`, `24h`, or custom date/time).
- Expired files are marked with status `expired`.
- Scheduled cleanup command removes expired files from storage and marks records as `deleted`.

Manual run:

```bash
php artisan files:cleanup-expired
```

Dry run:

```bash
php artisan files:cleanup-expired --dry-run
```

## OTP and SMS (Phase 3)

Implemented:
- Login with OTP via mobile: `/login/otp`
- Registration with OTP via mobile: `/register/otp`
- Mobile normalization for Iranian numbers (`09xxxxxxxxx`)
- Pluggable SMS provider structure (`log` and `smsir`)
- Optional SMS notification when receiving a file

Admin settings now include:
- OTP controls: TTL, code length, max attempts, resend cooldown
- SMS driver selection (`log` / `smsir`)
- SMS.ir config fields (API key, template id, endpoints, etc.)
- test SMS action in admin settings

For local development, keep `sms_driver=log` so OTP codes are written to `storage/logs/laravel.log`.

If `SMS.ir` test returns `cURL error 60` on Windows/WAMP:
- set `curl.cainfo` and `openssl.cafile` in PHP `php.ini` to a valid `cacert.pem`
- or set `sms_ca_bundle_path` in admin settings
- for local temporary testing only, you can disable `sms_ssl_verify`

## Download password protection (Phase 4)

Implemented:
- Optional download password when sending a file
- Password hash storage in `files.download_password_hash`
- Unlock page before download for protected files
- Session-based unlock per file after correct password

Routes:
- `GET /file-sends/{fileSend}/unlock`
- `POST /file-sends/{fileSend}/unlock`
- `GET /file-sends/{fileSend}/download`

## File preview (Phase 5)

Implemented:
- Preview route for conversation participants: `GET /file-sends/{fileSend}/preview`
- Image inline preview in conversation (`jpg/jpeg/png/gif/webp/bmp`)
- PDF inline preview in conversation (iframe)
- Policy-based preview checks (enabled flag, max size, type support)
- Password-protected files require unlock before preview

Admin settings now include:
- `preview_enabled`
- `preview_pdf_enabled`
- `preview_max_size_mb`
- `preview_image_extensions`

## Public download link (Phase 6)

Implemented:
- Optional public link creation at send time (no login required for downloader)
- Public short-link style landing page with token:
  - `GET /p/{token}` (download page)
  - `POST /p/{token}` (password verification)
  - `GET /p/{token}/preview` (inline preview when allowed)
  - `GET /p/{token}/file` (actual file download)
- Link expiry support and optional max download count
- Sender controls in conversation:
  - copy public link to clipboard
  - disable public link
  - regenerate public link token
- Public download logging in `file_send_public_downloads`

Data model additions:
- `file_sends.public_token`
- `file_sends.public_link_enabled`
- `file_sends.public_link_expires_at`
- `file_sends.public_max_downloads`
- `file_sends.public_download_count`
- `file_sends.public_last_downloaded_at`

## History and multi-recipient send (Phase 7)

Implemented:
- Multi-recipient send in one action (single upload, multiple receivers)
- Receiver parsing by `username/email/mobile` with comma/space separation
- Per-receiver send record, notification, email, and optional SMS
- History page with filters:
  - route: `GET /history`
  - direction: all/sent/received
- status: read/unread/downloaded/not_downloaded/public/active/expired/deleted
- search by file name or username

## Large file upload - chunked core (Phase 8, step 1)

Implemented:
- Chunked upload endpoints:
  - `POST /uploads/chunk/start`
  - `POST /uploads/chunk/status`
  - `POST /uploads/chunk/part`
  - `POST /uploads/chunk/finish`
- Send wizard integration:
  - For files above threshold, upload runs in chunks before final send submit
  - Upload progress bar in send form
  - Basic resume support via browser localStorage + server chunk status
- Finalization flow:
  - Chunks are assembled server-side
  - Final file is moved into normal `files/Y/m` storage flow

Admin settings now include:
- `chunk_upload_threshold_mb` (when chunk mode starts)
- `chunk_upload_size_mb` (size of each uploaded chunk)

## Phase 8 completion (queue, bandwidth, security scan)

Implemented:
- Queue-based post-upload security processing via `ScanUploadedFileJob`
- Basic security scan (`FileSecurityScanner`) with:
  - blocked extension policy
  - EICAR test-string detection
- Automatic block flow for unsafe files:
  - file status moved to deleted
  - storage file removed
- Chunk upload bandwidth limiter middleware on chunk part endpoint
  - configurable MB per minute cap

New admin settings:
- `chunk_upload_max_mb_per_minute`
- `security_scan_enabled`
- `security_scan_driver` (currently `basic`)
- `security_blocked_extensions`

## PWA setup (Phase 9)

Implemented:
- Dynamic web app manifest: `GET /manifest.webmanifest`
- Dynamic service worker: `GET /sw.js`
- Dynamic PWA logos:
  - `GET /pwa/logo/mobile`
  - `GET /pwa/logo/desktop`
  - `GET /pwa/logo/retina`
- In-app install popup for first run:
  - custom prompt using `beforeinstallprompt`
  - localStorage-based first-run suppression
  - iOS fallback install guidance

Admin settings now include:
- `app_display_name`
- `app_short_name`
- `pwa_enabled`
- `pwa_install_popup_enabled`
- `pwa_theme_color`
- `pwa_background_color`
- logo uploads: mobile / desktop / retina

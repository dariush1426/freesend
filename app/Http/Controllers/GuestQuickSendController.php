<?php

namespace App\Http\Controllers;

use App\Jobs\ScanUploadedFileJob;
use App\Mail\FileReceivedMail;
use App\Models\AppNotification;
use App\Models\FileSend;
use App\Models\Setting;
use App\Models\SharedFile;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\MobileNumber;
use App\Support\Sms\SmsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GuestQuickSendController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($this->quickSendEnabled(), 404);

        return view('guest.quick-send', [
            'maxFileSizeMb' => $this->quickSendMaxFileSizeMb(),
            'allowedExtensions' => Setting::getValue('allowed_extensions', ''),
            'defaultExpireHours' => $this->quickSendDefaultExpireHours(),
            'prefilledReceiver' => trim((string) $request->query('receiver', '')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->quickSendEnabled(), 404);

        $maxFileSizeMb = $this->quickSendMaxFileSizeMb();
        $allowedExtensions = collect(explode(',', Setting::getValue('allowed_extensions', '')))
            ->map(fn (string $extension) => trim(strtolower($extension)))
            ->filter()
            ->values();

        $validated = $request->validate([
            'sender_name' => ['required', 'string', 'max:120'],
            'sender_contact' => ['nullable', 'string', 'max:120'],
            'receiver' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:'.($maxFileSizeMb * 1024)],
        ]);

        $receiver = $this->resolveReceiver((string) $validated['receiver']);

        if (! $receiver) {
            return back()->withInput()->withErrors([
                'receiver' => __('messages.quick_send.receiver_not_found'),
            ]);
        }

        $uploadedFile = $request->file('file');
        if (! $uploadedFile) {
            return back()->withInput()->withErrors([
                'file' => __('messages.upload.file_missing'),
            ]);
        }

        $extension = mb_strtolower($uploadedFile->getClientOriginalExtension());

        if ($allowedExtensions->isNotEmpty() && ! $allowedExtensions->contains($extension)) {
            return back()->withInput()->withErrors([
                'file' => __('messages.upload.format_invalid', ['items' => $allowedExtensions->implode(', ')]),
            ]);
        }

        $directory = 'files/'.now()->format('Y/m');
        $storedName = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $storagePath = $uploadedFile->storeAs($directory, $storedName);
        $expiresAt = now()->addHours($this->quickSendDefaultExpireHours());

        $sharedFile = SharedFile::create([
            'owner_id' => $receiver->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => (string) ($uploadedFile->getMimeType() ?? ''),
            'extension' => $extension,
            'size' => (int) $uploadedFile->getSize(),
            'storage_path' => $storagePath,
            'checksum' => hash_file('sha256', Storage::path($storagePath)),
            'expires_at' => $expiresAt,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_PENDING,
        ]);

        ScanUploadedFileJob::dispatchSync($sharedFile->id);

        $fileSend = FileSend::create([
            'file_id' => $sharedFile->id,
            'sender_id' => null,
            'sender_name' => trim((string) $validated['sender_name']),
            'sender_contact' => trim((string) ($validated['sender_contact'] ?? '')) ?: null,
            'receiver_id' => $receiver->id,
            'message' => $validated['message'] ?? null,
            'public_link_enabled' => false,
            'public_download_count' => 0,
        ]);

        $this->notifyReceiver($fileSend);

        return redirect()
            ->route('quick-send.create')
            ->with('status', __('messages.quick_send.sent'))
            ->with('quick_send_result', [
                'receiver' => $receiver->username,
                'file_name' => $sharedFile->original_name,
                'expires_at' => \App\Support\LocalizedDate::dateTime($expiresAt),
                'sender_name' => trim((string) $validated['sender_name']),
                'sender_contact' => trim((string) ($validated['sender_contact'] ?? '')),
            ]);
    }

    private function resolveReceiver(string $token): ?User
    {
        $normalizedMobile = MobileNumber::normalize($token);

        return User::query()
            ->where(function ($builder) use ($token, $normalizedMobile): void {
                $builder
                    ->where('username', $token)
                    ->orWhere('email', $token)
                    ->orWhere('mobile', $token);

                if ($normalizedMobile) {
                    $builder->orWhere('mobile', $normalizedMobile);
                }
            })
            ->first(['id', 'username', 'full_name', 'email', 'mobile']);
    }

    private function notifyReceiver(FileSend $fileSend): void
    {
        $receiver = $fileSend->receiver()->first();
        if (! $receiver) {
            return;
        }

        $senderName = $fileSend->senderDisplayName();

        AppNotification::create([
            'user_id' => $receiver->id,
            'type' => 'file_received',
            'title' => __('messages.file_send.notification_title'),
            'body' => __('messages.file_send.notification_body', ['sender' => $senderName]),
            'payload' => [
                'file_send_id' => $fileSend->id,
                'sender_id' => null,
                'guest_sender' => true,
                'title_key' => 'messages.file_send.notification_title',
                'title_params' => [],
                'body_key' => 'messages.file_send.notification_body',
                'body_params' => ['sender' => $senderName],
            ],
        ]);

        if (Setting::getValue('email_notification_enabled', 'true') === 'true' && ! empty($receiver->email)) {
            try {
                MailSettings::apply();
                Mail::to($receiver->email)->send(new FileReceivedMail($fileSend));
            } catch (Throwable $exception) {
                Log::warning('Guest quick send notification email failed', [
                    'file_send_id' => $fileSend->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (
            Setting::getValue('sms_notification_enabled', 'false') === 'true'
            && ! empty($receiver->mobile)
        ) {
            try {
                SmsManager::send(
                    $receiver->mobile,
                    __('messages.file_send.sms_received', ['sender' => $senderName])
                );
            } catch (Throwable $exception) {
                Log::warning('Guest quick send notification sms failed', [
                    'file_send_id' => $fileSend->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function quickSendEnabled(): bool
    {
        return Setting::getValue('quick_send_enabled', 'true') === 'true';
    }

    private function quickSendMaxFileSizeMb(): int
    {
        return max(1, (int) Setting::getValue('quick_send_max_file_size_mb', '10'));
    }

    private function quickSendDefaultExpireHours(): int
    {
        return max(1, (int) Setting::getValue('quick_send_default_expire_hours', '1'));
    }
}

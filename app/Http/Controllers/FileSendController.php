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
use App\Support\PersonalStorageQuota;
use App\Support\PlanPolicy;
use App\Support\Sms\SmsManager;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class FileSendController extends Controller
{
    public function create(Request $request): View
    {
        $planProfile = PlanPolicy::profileForUser($request->user());
        $defaultExpireHours = max(1, (int) Setting::getValue('default_expire_hours', '24'));
        $publicLinksEnabledByAdmin = Setting::getValue('public_link_enabled', 'false') === 'true';
        $publicLinkFeatureEnabled = $publicLinksEnabledByAdmin && $planProfile['allow_public_links'];
        $publicLinkFeatureNotice = $publicLinkFeatureEnabled
            ? null
            : ($publicLinksEnabledByAdmin ? __('ui.send.public_link_disabled_by_plan') : __('ui.send.public_link_disabled'));
        $effectiveMaxFileSizeMb = PlanPolicy::effectiveMaxUploadSizeMb(
            $request->user(),
            max(1, (int) Setting::getValue('max_file_size_mb', '20'))
        );
        $prefilledReceiver = trim((string) $request->query('receiver', ''));
        $prefilledReceiverUser = $prefilledReceiver !== ''
            ? $this->resolveSingleReceiver($prefilledReceiver)
            : null;
        $receiverLocked = $prefilledReceiverUser !== null;
        $personalStorageEnabled = (bool) ($planProfile['allow_personal_storage'] ?? false);
        $defaultDestination = $personalStorageEnabled
            && ! $receiverLocked
            && $request->query('destination') === 'storage'
                ? 'storage'
                : 'send';

        return view('files.send', [
            'maxFileSizeMb' => $effectiveMaxFileSizeMb,
            'allowedExtensions' => Setting::getValue('allowed_extensions', ''),
            'defaultExpireHours' => $defaultExpireHours,
            'expireOptions' => $this->expireOptions($defaultExpireHours, $planProfile['expire_options']),
            'publicLinkFeatureEnabled' => $publicLinkFeatureEnabled,
            'publicLinkFeatureNotice' => $publicLinkFeatureNotice,
            'chunkUploadThresholdBytes' => max(1, (int) Setting::getValue('chunk_upload_threshold_mb', '8')) * 1024 * 1024,
            'chunkUploadSizeBytes' => max(1, (int) Setting::getValue('chunk_upload_size_mb', '2')) * 1024 * 1024,
            'prefilledReceiver' => $receiverLocked ? $prefilledReceiverUser->username : $prefilledReceiver,
            'prefilledReceiverUser' => $prefilledReceiverUser,
            'receiverLocked' => $receiverLocked,
            'personalStorageEnabled' => $personalStorageEnabled,
            'defaultDestination' => $defaultDestination,
            'planProfile' => $planProfile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $planProfile = PlanPolicy::profileForUser($request->user());
        $destination = in_array((string) $request->input('destination', 'send'), ['send', 'storage'], true)
            ? (string) $request->input('destination', 'send')
            : 'send';
        $maxFileSizeMb = PlanPolicy::effectiveMaxUploadSizeMb(
            $request->user(),
            max(1, (int) Setting::getValue('max_file_size_mb', '20'))
        );
        $allowedExtensions = collect(explode(',', Setting::getValue('allowed_extensions', '')))
            ->map(fn (string $extension) => trim(strtolower($extension)))
            ->filter()
            ->values();

        $rules = [
            'destination' => ['nullable', 'string', 'in:send,storage'],
            'message' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'required_without:uploaded_file_token', 'max:'.($maxFileSizeMb * 1024)],
            'uploaded_file_token' => ['nullable', 'string', 'required_without:file', 'max:120'],
        ];

        if ($destination === 'send') {
            $allowedExpireOptions = $planProfile['expire_options'];
            $rules += [
                'receiver' => ['required', 'string', 'max:255'],
                'expire_option' => ['required', 'string', 'in:'.implode(',', $allowedExpireOptions)],
                'custom_expires_at' => ['nullable', 'date', 'required_if:expire_option,custom', 'after:now'],
                'download_password' => ['nullable', 'string', 'min:4', 'max:120', 'confirmed'],
                'public_link_enabled' => ['nullable', 'boolean'],
                'public_link_expires_at' => ['nullable', 'date', 'after:now'],
                'public_link_max_downloads' => ['nullable', 'integer', 'min:1', 'max:100000'],
            ];
        }

        $validated = $request->validate($rules);

        if ($destination === 'storage' && ! ($planProfile['allow_personal_storage'] ?? false)) {
            return back()->withInput()->withErrors([
                'destination' => __('messages.personal_storage.disabled_by_plan'),
            ]);
        }

        if (
            $destination === 'send'
            && ! $planProfile['allow_password_protection']
            && ! empty($validated['download_password'])
        ) {
            return back()->withInput()->withErrors([
                'download_password' => __('messages.file_send.password_disabled_by_plan'),
            ]);
        }

        $uploadData = $this->resolveUploadData($request, $validated, $maxFileSizeMb, $allowedExtensions);

        if (isset($uploadData['error'])) {
            return back()->withInput()->withErrors([
                'file' => (string) $uploadData['error'],
            ]);
        }

        if ($destination === 'storage') {
            if (! PersonalStorageQuota::canStoreUpload($request->user(), (int) $uploadData['size'])) {
                if (! empty($uploadData['storage_path']) && Storage::exists((string) $uploadData['storage_path'])) {
                    Storage::delete((string) $uploadData['storage_path']);
                }

                $storageProfile = PersonalStorageQuota::profileForUser($request->user());

                return back()->withInput()->withErrors([
                    'file' => __('messages.personal_storage.quota_reached', [
                        'size' => number_format((int) ($storageProfile['quota_mb'] ?? 0)),
                    ]),
                ]);
            }

            $sharedFile = SharedFile::create([
                'owner_id' => Auth::id(),
                'is_personal_storage' => true,
                'original_name' => (string) $uploadData['original_name'],
                'stored_name' => (string) $uploadData['stored_name'],
                'mime_type' => (string) ($uploadData['mime_type'] ?? ''),
                'extension' => (string) ($uploadData['extension'] ?? ''),
                'size' => (int) $uploadData['size'],
                'storage_path' => (string) $uploadData['storage_path'],
                'checksum' => hash_file('sha256', Storage::path((string) $uploadData['storage_path'])),
                'expires_at' => null,
                'status' => SharedFile::STATUS_ACTIVE,
                'security_scan_status' => SharedFile::SECURITY_SCAN_PENDING,
                'download_password_hash' => null,
            ]);

            ScanUploadedFileJob::dispatch($sharedFile->id);

            return redirect()
                ->route('storage.index')
                ->with('status', __('messages.personal_storage.stored'));
        }

        $receivers = $this->resolveReceivers((string) $validated['receiver']);

        if (! empty($receivers['invalid_tokens'])) {
            return back()->withInput()->withErrors([
                'receiver' => __('messages.file_send.receivers_not_found', ['items' => implode(', ', $receivers['invalid_tokens'])]),
            ]);
        }

        if (empty($receivers['users'])) {
            return back()->withInput()->withErrors([
                'receiver' => __('messages.file_send.receiver_required'),
            ]);
        }

        $expiresAt = $this->resolveExpiresAt($validated);

        $sharedFile = SharedFile::create([
            'owner_id' => Auth::id(),
            'is_personal_storage' => false,
            'original_name' => (string) $uploadData['original_name'],
            'stored_name' => (string) $uploadData['stored_name'],
            'mime_type' => (string) ($uploadData['mime_type'] ?? ''),
            'extension' => (string) ($uploadData['extension'] ?? ''),
            'size' => (int) $uploadData['size'],
            'storage_path' => (string) $uploadData['storage_path'],
            'checksum' => hash_file('sha256', Storage::path((string) $uploadData['storage_path'])),
            'expires_at' => $expiresAt,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_PENDING,
            'download_password_hash' => ! empty($validated['download_password'])
                ? Hash::make($validated['download_password'])
                : null,
        ]);

        ScanUploadedFileJob::dispatch($sharedFile->id);

        $publicLinkRequested = $request->boolean('public_link_enabled');
        $publicLinkAllowed = Setting::getValue('public_link_enabled', 'false') === 'true'
            && $planProfile['allow_public_links'];
        $publicLinkEnabled = $publicLinkRequested && $publicLinkAllowed;
        $publicLinkExpiresAt = $this->resolvePublicLinkExpiresAt($validated, $expiresAt, $publicLinkEnabled);
        $publicMaxDownloads = $publicLinkEnabled
            ? (! empty($validated['public_link_max_downloads']) ? (int) $validated['public_link_max_downloads'] : null)
            : null;

        $fileSends = collect($receivers['users'])
            ->map(function (User $receiver) use (
                $sharedFile,
                $validated,
                $publicLinkEnabled,
                $publicLinkExpiresAt,
                $publicMaxDownloads
            ): FileSend {
                return FileSend::create([
                    'file_id' => $sharedFile->id,
                    'sender_id' => Auth::id(),
                    'receiver_id' => $receiver->id,
                    'message' => $validated['message'] ?? null,
                    'public_token' => $publicLinkEnabled ? FileSend::generatePublicToken() : null,
                    'public_link_enabled' => $publicLinkEnabled,
                    'public_link_expires_at' => $publicLinkExpiresAt,
                    'public_max_downloads' => $publicMaxDownloads,
                    'public_download_count' => 0,
                ]);
            })
            ->values();

        foreach ($fileSends as $fileSend) {
            $this->notifyReceiver($fileSend);
        }

        $status = $fileSends->count() > 1
            ? __('messages.file_send.sent_multiple', ['count' => $fileSends->count()])
            : __('messages.file_send.sent_single');

        if ($fileSends->count() === 1) {
            $receiver = $receivers['users'][0];
            $redirect = redirect()
                ->route('conversations.show', $receiver)
                ->with('status', $status);

            $firstSend = $fileSends->first();

            if ($firstSend && $firstSend->public_link_enabled && $firstSend->public_token) {
                $redirect->with('public_link_url', route('public-files.download', $firstSend->public_token));
            }

            return $redirect;
        }

        $receiverMap = collect($receivers['users'])->keyBy('id');

        $publicLinks = $fileSends
            ->filter(fn (FileSend $send) => $send->public_link_enabled && ! empty($send->public_token))
            ->map(function (FileSend $send) use ($receiverMap): array {
                return [
                    'receiver' => (string) (($receiverMap->get($send->receiver_id)?->username) ?? $send->receiver_id),
                    'url' => route('public-files.download', (string) $send->public_token),
                ];
            })
            ->values()
            ->all();

        return redirect()
            ->route('inbox')
            ->with('status', $status)
            ->with('public_links', $publicLinks);
    }

    private function expireOptions(int $defaultExpireHours, array $allowedOptions): array
    {
        $labels = [
            'default' => __('ui.send.default_expire_with_hours', ['hours' => $defaultExpireHours]),
            '1' => __('ui.send.expire_1_hour'),
            '2' => __('ui.send.expire_2_hours'),
            '5' => __('ui.send.expire_5_hours'),
            '12' => __('ui.send.expire_12_hours'),
            '24' => __('ui.send.expire_24_hours'),
            'custom' => __('ui.send.custom_expire'),
            'never' => __('ui.send.never_expire'),
        ];

        return collect($allowedOptions)
            ->filter(fn (string $value) => array_key_exists($value, $labels))
            ->mapWithKeys(fn (string $value) => [$value => $labels[$value]])
            ->all();
    }

    private function resolveExpiresAt(array $validated): ?Carbon
    {
        $option = (string) ($validated['expire_option'] ?? 'default');
        $defaultHours = max(1, (int) Setting::getValue('default_expire_hours', '24'));

        if ($option === 'never') {
            return null;
        }

        if ($option === 'custom') {
            return Carbon::parse((string) $validated['custom_expires_at']);
        }

        if ($option === 'default') {
            return now()->addHours($defaultHours);
        }

        return now()->addHours((int) $option);
    }

    private function resolvePublicLinkExpiresAt(array $validated, ?Carbon $fileExpiresAt, bool $enabled): ?Carbon
    {
        if (! $enabled) {
            return null;
        }

        if (! empty($validated['public_link_expires_at'])) {
            $requested = Carbon::parse((string) $validated['public_link_expires_at']);

            if (! $fileExpiresAt) {
                return $requested;
            }

            return $requested->greaterThan($fileExpiresAt)
                ? $fileExpiresAt
                : $requested;
        }

        return $fileExpiresAt;
    }

    private function resolveReceivers(string $rawInput): array
    {
        $tokens = collect(preg_split('/[\s,;]+/u', $rawInput) ?: [])
            ->map(fn (string $token) => trim($token))
            ->filter()
            ->unique()
            ->values();

        $users = [];
        $invalidTokens = [];

        foreach ($tokens as $token) {
            $normalizedMobile = MobileNumber::normalize($token);

            $user = User::query()
                ->where('id', '!=', Auth::id())
                ->where(function ($builder) use ($token, $normalizedMobile): void {
                    $builder
                        ->where('username', $token)
                        ->orWhere('email', $token)
                        ->orWhere('mobile', $token);

                    if ($normalizedMobile) {
                        $builder->orWhere('mobile', $normalizedMobile);
                    }
                })
                ->first();

            if (! $user) {
                $invalidTokens[] = $token;
                continue;
            }

            $users[$user->id] = $user;
        }

        return [
            'users' => array_values($users),
            'invalid_tokens' => $invalidTokens,
        ];
    }

    private function resolveSingleReceiver(string $token): ?User
    {
        $normalizedMobile = MobileNumber::normalize($token);

        return User::query()
            ->where('id', '!=', Auth::id())
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
                'sender_id' => Auth::id(),
                'title_key' => 'messages.file_send.notification_title',
                'title_params' => [],
                'body_key' => 'messages.file_send.notification_body',
                'body_params' => ['sender' => $senderName],
            ],
        ]);

        if (Setting::getValue('email_notification_enabled', 'true') === 'true') {
            try {
                MailSettings::apply();
                Mail::to($receiver->email)->send(new FileReceivedMail($fileSend));
            } catch (Throwable $exception) {
                Log::warning('File notification email failed', [
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
                Log::warning('File notification sms failed', [
                    'file_send_id' => $fileSend->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function resolveUploadData(
        Request $request,
        array $validated,
        int $maxFileSizeMb,
        $allowedExtensions
    ): array {
        $directory = 'files/'.now()->format('Y/m');

        if (! empty($validated['uploaded_file_token'])) {
            $chunkData = Cache::pull($this->chunkTokenCacheKey((string) $validated['uploaded_file_token']));

            if (! is_array($chunkData)) {
                return ['error' => __('messages.upload.token_invalid')];
            }

            if ((int) ($chunkData['owner_id'] ?? 0) !== Auth::id()) {
                return ['error' => __('messages.upload.token_owner')];
            }

            $tempPath = (string) ($chunkData['path'] ?? '');

            if ($tempPath === '' || ! Storage::exists($tempPath)) {
                return ['error' => __('messages.upload.file_missing_server')];
            }

            $size = (int) ($chunkData['size'] ?? 0);
            $extension = mb_strtolower((string) ($chunkData['extension'] ?? ''));

            if ($size < 1 || $size > ($maxFileSizeMb * 1024 * 1024)) {
                Storage::delete($tempPath);
                return ['error' => __('messages.upload.size_invalid')];
            }

            if ($allowedExtensions->isNotEmpty() && ! $allowedExtensions->contains($extension)) {
                Storage::delete($tempPath);
                return ['error' => __('messages.upload.format_invalid', ['items' => $allowedExtensions->implode(', ')])];
            }

            $storedName = Str::uuid().($extension !== '' ? '.'.$extension : '');
            $storagePath = $directory.'/'.$storedName;
            Storage::makeDirectory($directory);

            if (! Storage::move($tempPath, $storagePath)) {
                return ['error' => __('messages.upload.move_failed')];
            }

            return [
                'original_name' => (string) ($chunkData['original_name'] ?? 'uploaded-file'),
                'stored_name' => $storedName,
                'mime_type' => (string) ($chunkData['mime_type'] ?? ''),
                'extension' => $extension,
                'size' => $size,
                'storage_path' => $storagePath,
            ];
        }

        $uploadedFile = $request->file('file');

        if (! $uploadedFile) {
            return ['error' => __('messages.upload.file_missing')];
        }

        $extension = mb_strtolower($uploadedFile->getClientOriginalExtension());

        if ($allowedExtensions->isNotEmpty() && ! $allowedExtensions->contains($extension)) {
            return ['error' => __('messages.upload.format_invalid', ['items' => $allowedExtensions->implode(', ')])];
        }

        $storedName = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $storagePath = $uploadedFile->storeAs($directory, $storedName);

        return [
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $extension,
            'size' => (int) $uploadedFile->getSize(),
            'storage_path' => $storagePath,
        ];
    }

    private function chunkTokenCacheKey(string $token): string
    {
        return 'chunk_upload_token:'.$token;
    }
}

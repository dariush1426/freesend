<?php

namespace App\Http\Controllers;

use App\Jobs\ScanUploadedFileJob;
use App\Mail\FileReceivedMail;
use App\Models\AppNotification;
use App\Models\FileStorageAccess;
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
use Illuminate\Validation\Rule;
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
        $systemMaxFileSizeMb = max(1, (int) Setting::getValue('max_file_size_mb', '20'));
        $senderMaxFileSizeMb = PlanPolicy::effectiveMaxUploadSizeMb(
            $request->user(),
            $systemMaxFileSizeMb
        );
        $prefilledReceiver = trim((string) $request->query('receiver', ''));
        $prefilledReceiverUser = $prefilledReceiver !== ''
            ? $this->resolveSingleReceiver($prefilledReceiver)
            : null;
        $receiverLocked = $prefilledReceiverUser !== null;
        $effectiveMaxFileSizeMb = $receiverLocked
            ? $this->effectiveMaxUploadSizeMbForSend($request->user(), [$prefilledReceiverUser], $systemMaxFileSizeMb)
            : $senderMaxFileSizeMb;
        $personalStorageEnabled = (bool) ($planProfile['allow_personal_storage'] ?? false);
        $senderNoExpiryCapabilities = $this->senderNoExpiryCapabilities($request->user());
        $prefilledReceiverCapabilities = $prefilledReceiverUser
            ? $this->receiverSendCapabilities([$prefilledReceiverUser])
            : $this->emptyReceiverSendCapabilities();
        $effectiveExpireOptions = $receiverLocked
            ? $this->expireOptions(
                $defaultExpireHours,
                $this->allowedExpireOptionsForSend(
                    $planProfile['expire_options'],
                    $prefilledReceiverCapabilities,
                    (bool) ($senderNoExpiryCapabilities['allow_never_expire'] ?? false)
                )
            )
            : $this->expireOptions(
                $defaultExpireHours,
                $this->allowedExpireOptionsForSend(
                    $planProfile['expire_options'],
                    $this->emptyReceiverSendCapabilities(),
                    (bool) ($senderNoExpiryCapabilities['allow_never_expire'] ?? false)
                )
            );
        $defaultDestination = $personalStorageEnabled
            && ! $receiverLocked
            && $request->query('destination') === 'storage'
                ? 'storage'
                : 'send';

        return view('files.send', [
            'maxFileSizeMb' => $effectiveMaxFileSizeMb,
            'allowedExtensions' => Setting::getValue('allowed_extensions', ''),
            'defaultExpireHours' => $defaultExpireHours,
            'expireOptions' => $effectiveExpireOptions,
            'senderExpireOptionValues' => array_values($planProfile['expire_options']),
            'allExpireOptions' => $this->expireOptions($defaultExpireHours, PlanPolicy::EXPIRE_OPTION_VALUES),
            'publicLinkFeatureEnabled' => $publicLinkFeatureEnabled,
            'publicLinkFeatureNotice' => $publicLinkFeatureNotice,
            'chunkUploadThresholdBytes' => max(1, (int) Setting::getValue('chunk_upload_threshold_mb', '8')) * 1024 * 1024,
            'chunkUploadSizeBytes' => $this->effectiveChunkUploadSizeBytes(),
            'prefilledReceiver' => $receiverLocked ? $prefilledReceiverUser->username : $prefilledReceiver,
            'prefilledReceiverUser' => $prefilledReceiverUser,
            'prefilledReceiverCapabilities' => $prefilledReceiverCapabilities,
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
        $systemMaxFileSizeMb = max(1, (int) Setting::getValue('max_file_size_mb', '20'));
        $senderMaxFileSizeMb = PlanPolicy::effectiveMaxUploadSizeMb(
            $request->user(),
            $systemMaxFileSizeMb
        );
        $allowedExtensions = collect(explode(',', Setting::getValue('allowed_extensions', '')))
            ->map(fn (string $extension) => trim(strtolower($extension)))
            ->filter()
            ->values();

        $rules = [
            'destination' => ['nullable', 'string', 'in:send,storage'],
            'message' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'max:'.($systemMaxFileSizeMb * 1024)],
            'uploaded_file_token' => ['nullable', 'string', 'max:120'],
        ];

        if ($destination === 'send') {
            $rules += [
                'receiver' => ['required', 'string', 'max:255'],
                'expire_option' => ['required', 'string', Rule::in(PlanPolicy::EXPIRE_OPTION_VALUES)],
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

        if ($destination === 'storage') {
            $hasUploadInput = ! empty($validated['uploaded_file_token']) || $request->hasFile('file');
            $storageMessage = trim((string) ($validated['message'] ?? ''));

            if (! $hasUploadInput && $storageMessage === '') {
                return back()->withInput()->withErrors([
                    'message' => __('messages.personal_storage.message_or_file_required'),
                ]);
            }

            $uploadData = $hasUploadInput
                ? $this->resolveUploadData($request, $validated, $senderMaxFileSizeMb, $allowedExtensions)
                : $this->buildTextNoteUploadData($storageMessage);

            if (isset($uploadData['error'])) {
                return back()->withInput()->withErrors([
                    $hasUploadInput ? 'file' : 'message' => (string) $uploadData['error'],
                ]);
            }

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
                'checksum' => (string) ($uploadData['checksum'] ?? hash_file('sha256', Storage::path((string) $uploadData['storage_path']))),
                'expires_at' => null,
                'status' => SharedFile::STATUS_ACTIVE,
                'security_scan_status' => (string) ($uploadData['security_scan_status'] ?? SharedFile::SECURITY_SCAN_PENDING),
                'security_scanned_at' => $uploadData['security_scanned_at'] ?? null,
                'download_password_hash' => null,
            ]);

        if (($uploadData['security_scan_status'] ?? SharedFile::SECURITY_SCAN_PENDING) === SharedFile::SECURITY_SCAN_PENDING) {
            ScanUploadedFileJob::dispatchSync($sharedFile->id);
        }

        $this->syncPersonalStorageWorkspaceAccess(
            $sharedFile,
            $request->user(),
            null,
            FileStorageAccess::CONTEXT_OWNED
        );
        $this->recordPersonalStorageExchange(
            $sharedFile,
            $request->user(),
            $storageMessage
        );
        PersonalStorageQuota::disableNoExpiryReceivingIfUnavailable($request->user());

        return redirect()
            ->route('storage.index')
                ->with('status', ! empty($uploadData['is_note'])
                    ? __('messages.personal_storage.stored_note')
                    : __('messages.personal_storage.stored'));
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

        $isMultiRecipient = count($receivers['users']) > 1;

        if ($isMultiRecipient && ! ($planProfile['allow_personal_storage'] ?? false)) {
            return back()->withInput()->withErrors([
                'receiver' => __('messages.file_send.multi_recipient_requires_storage'),
            ]);
        }

        $receiverCapabilities = $this->receiverSendCapabilities($receivers['users']);
        $senderNoExpiryCapabilities = $this->senderNoExpiryCapabilities($request->user());
        $allowedExpireOptions = $this->allowedExpireOptionsForSend(
            $planProfile['expire_options'],
            $receiverCapabilities,
            (bool) ($senderNoExpiryCapabilities['allow_never_expire'] ?? false)
        );
        $selectedExpireOption = (string) ($validated['expire_option'] ?? 'default');

        if (! in_array($selectedExpireOption, $allowedExpireOptions, true)) {
            return back()->withInput()->withErrors([
                'expire_option' => __('messages.file_send.expire_not_allowed'),
            ]);
        }

        $hasUploadInput = ! empty($validated['uploaded_file_token']) || $request->hasFile('file');
        $sendMessage = trim((string) ($validated['message'] ?? ''));
        $allowNoteWithoutFile = (bool) ($receiverCapabilities['allow_note_without_file'] ?? false);

        if (! $hasUploadInput && ! $allowNoteWithoutFile) {
            return back()->withInput()->withErrors([
                'file' => __('messages.upload.file_missing'),
            ]);
        }

        if (! $hasUploadInput && $sendMessage === '') {
            return back()->withInput()->withErrors([
                'message' => __('messages.personal_storage.message_or_file_required'),
            ]);
        }

        $sendMaxFileSizeMb = $this->effectiveMaxUploadSizeMbForSend(
            $request->user(),
            $receivers['users'],
            $systemMaxFileSizeMb
        );

        $uploadData = $hasUploadInput
            ? $this->resolveUploadData($request, $validated, $sendMaxFileSizeMb, $allowedExtensions)
            : $this->buildTextNoteUploadData($sendMessage);

        if (isset($uploadData['error'])) {
            return back()->withInput()->withErrors([
                $hasUploadInput ? 'file' : 'message' => (string) $uploadData['error'],
            ]);
        }

        $receiverCapabilities = $this->receiverSendCapabilities($receivers['users'], (int) $uploadData['size']);
        $senderNoExpiryCapabilities = $this->senderNoExpiryCapabilities($request->user(), (int) $uploadData['size']);
        $senderCanStoreNoExpiry = (bool) ($senderNoExpiryCapabilities['store_in_sender_storage'] ?? false);

        if (
            $selectedExpireOption === 'never'
            && ! ($receiverCapabilities['allow_never_expire'] ?? false)
            && ! $senderCanStoreNoExpiry
        ) {
            return back()->withInput()->withErrors([
                'expire_option' => __('messages.file_send.receiver_storage_unavailable'),
            ]);
        }

        $singleReceiver = count($receivers['users']) === 1 ? $receivers['users'][0] : null;
        $storeInReceiverStorage = $singleReceiver !== null
            && $selectedExpireOption === 'never'
            && (bool) ($receiverCapabilities['store_in_receiver_storage'] ?? false);
        $storeInSenderStorage = ! $storeInReceiverStorage
            && $selectedExpireOption === 'never'
            && $senderCanStoreNoExpiry;
        $expiresAt = ($storeInReceiverStorage || $storeInSenderStorage) ? null : $this->resolveExpiresAt($validated);

        $sharedFile = SharedFile::create([
            'owner_id' => $storeInReceiverStorage ? $singleReceiver->id : Auth::id(),
            'is_personal_storage' => $storeInReceiverStorage || $storeInSenderStorage,
            'original_name' => (string) $uploadData['original_name'],
            'stored_name' => (string) $uploadData['stored_name'],
            'mime_type' => (string) ($uploadData['mime_type'] ?? ''),
            'extension' => (string) ($uploadData['extension'] ?? ''),
            'size' => (int) $uploadData['size'],
            'storage_path' => (string) $uploadData['storage_path'],
            'checksum' => (string) ($uploadData['checksum'] ?? hash_file('sha256', Storage::path((string) $uploadData['storage_path']))),
            'expires_at' => $expiresAt,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => (string) ($uploadData['security_scan_status'] ?? SharedFile::SECURITY_SCAN_PENDING),
            'security_scanned_at' => $uploadData['security_scanned_at'] ?? null,
            'download_password_hash' => ! empty($validated['download_password'])
                ? Hash::make($validated['download_password'])
                : null,
        ]);

        if (($uploadData['security_scan_status'] ?? SharedFile::SECURITY_SCAN_PENDING) === SharedFile::SECURITY_SCAN_PENDING) {
            ScanUploadedFileJob::dispatchSync($sharedFile->id);
        }

        if ($sharedFile->is_personal_storage) {
            $workspaceContext = $storeInReceiverStorage
                ? FileStorageAccess::CONTEXT_RECEIVED
                : FileStorageAccess::CONTEXT_SENT;

            $this->syncPersonalStorageWorkspaceAccess(
                $sharedFile,
                $request->user(),
                $singleReceiver,
                $workspaceContext
            );

            PersonalStorageQuota::disableNoExpiryReceivingIfUnavailable($sharedFile->owner);
        }

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
            $firstSend = $fileSends->first();
            $redirect = redirect()
                ->route('conversations.show', array_filter([
                    'user' => $receiver,
                    'highlight' => $firstSend?->id,
                ]))
                ->with('status', $status);

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
            ->filter(fn (mixed $value) => is_string($value) && array_key_exists($value, $labels))
            ->mapWithKeys(fn (mixed $value) => [(string) $value => $labels[(string) $value]])
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
            ->first(['id', 'username', 'full_name', 'email', 'mobile', 'allow_receive_no_expiry']);
    }

    private function senderNoExpiryCapabilities(User $user, ?int $incomingSize = null): array
    {
        $planProfile = PlanPolicy::profileForUser($user);
        $storageProfile = PersonalStorageQuota::profileForUser($user);
        $supportsPersonalStorage = (bool) ($planProfile['allow_personal_storage'] ?? false);
        $supportsNeverExpire = (bool) ($planProfile['allow_never_expire'] ?? false);
        $storageFull = $supportsPersonalStorage
            && $storageProfile['quota_bytes'] !== null
            && (int) ($storageProfile['remaining_bytes'] ?? 0) < 1;
        $canFitIncoming = $supportsPersonalStorage
            && ! $storageFull
            && ($incomingSize === null || PersonalStorageQuota::canStoreUpload($user, $incomingSize));

        return [
            'allow_never_expire' => $supportsNeverExpire && $canFitIncoming,
            'store_in_sender_storage' => $supportsNeverExpire && $canFitIncoming,
            'storage_full' => $storageFull,
        ];
    }

    private function syncPersonalStorageWorkspaceAccess(
        SharedFile $file,
        User $sender,
        ?User $receiver = null,
        string $ownerContext = FileStorageAccess::CONTEXT_OWNED
    ): void {
        if (! $file->is_personal_storage) {
            return;
        }

        FileStorageAccess::query()->updateOrCreate(
            [
                'file_id' => $file->id,
                'user_id' => $file->owner_id,
            ],
            [
                'role' => FileStorageAccess::ROLE_OWNER,
                'context' => $ownerContext,
            ]
        );

        $senderProfile = PlanPolicy::profileForUser($sender);
        $senderCanUseWorkspace = (bool) ($senderProfile['allow_personal_storage'] ?? false);

        if ($senderCanUseWorkspace && $sender->id !== $file->owner_id) {
            FileStorageAccess::query()->updateOrCreate(
                [
                    'file_id' => $file->id,
                    'user_id' => $sender->id,
                ],
                [
                    'role' => FileStorageAccess::ROLE_MANAGER,
                    'context' => FileStorageAccess::CONTEXT_SENT,
                ]
            );
        }

        if (! $receiver || $receiver->id === $file->owner_id) {
            return;
        }

        $receiverProfile = PlanPolicy::profileForUser($receiver);
        $receiverCanUseWorkspace = (bool) ($receiverProfile['allow_personal_storage'] ?? false)
            && (bool) $receiver->allow_receive_no_expiry;

        if (! $receiverCanUseWorkspace) {
            return;
        }

        FileStorageAccess::query()->updateOrCreate(
            [
                'file_id' => $file->id,
                'user_id' => $receiver->id,
            ],
            [
                'role' => FileStorageAccess::ROLE_MANAGER,
                'context' => FileStorageAccess::CONTEXT_RECEIVED,
            ]
        );
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

    private function recordPersonalStorageExchange(SharedFile $file, User $user, string $message = ''): void
    {
        $fileSend = FileSend::query()->create([
            'file_id' => $file->id,
            'sender_id' => $user->id,
            'receiver_id' => $user->id,
            'message' => $message !== '' ? $message : null,
        ]);

        $title = app()->getLocale() === 'fa'
            ? 'فایل به فضای ذخیره‌سازی ارسال شد'
            : 'File sent to personal storage';
        $body = app()->getLocale() === 'fa'
            ? 'ارسال جدید شما در تبادل فضای شخصی ثبت شد.'
            : 'Your new send was recorded in the personal storage exchange.';

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'personal_storage_received',
            'title' => $title,
            'body' => $body,
            'payload' => [
                'file_send_id' => $fileSend->id,
                'storage_thread' => true,
                'file_id' => $file->id,
            ],
        ]);
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

    private function effectiveChunkUploadSizeBytes(): int
    {
        $configured = max(1, (int) Setting::getValue('chunk_upload_size_mb', '2')) * 1024 * 1024;
        $phpLimit = min(
            $this->phpIniSizeToBytes('upload_max_filesize'),
            $this->phpIniSizeToBytes('post_max_size')
        );

        if ($phpLimit === PHP_INT_MAX) {
            return $configured;
        }

        if ($phpLimit < 128 * 1024) {
            return max(1, min($configured, $phpLimit));
        }

        $safeLimit = min((int) floor($phpLimit * 0.85), $phpLimit - (128 * 1024));

        return max(64 * 1024, min($configured, $safeLimit));
    }

    private function phpIniSizeToBytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '-1' || $value === '0') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function buildTextNoteUploadData(string $content): array
    {
        $normalizedContent = trim($content);

        if ($normalizedContent === '') {
            return ['error' => __('messages.personal_storage.message_or_file_required')];
        }

        $directory = 'files/'.now()->format('Y/m');
        $storedName = Str::uuid().'.txt';
        $storagePath = $directory.'/'.$storedName;
        $originalName = 'note-'.now()->format('Ymd-His').'.txt';

        Storage::makeDirectory($directory);

        if (! Storage::put($storagePath, $normalizedContent)) {
            return ['error' => __('messages.upload.move_failed')];
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => strlen($normalizedContent),
            'storage_path' => $storagePath,
            'checksum' => hash('sha256', $normalizedContent),
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
            'security_scanned_at' => now(),
            'is_note' => true,
        ];
    }

    private function effectiveMaxUploadSizeMbForSend(User $sender, array $receivers, int $systemMaxMb): int
    {
        $senderMaxMb = PlanPolicy::effectiveMaxUploadSizeMb($sender, $systemMaxMb);

        if (count($receivers) !== 1) {
            return $senderMaxMb;
        }

        $receiver = $receivers[0];
        $receiverProfile = PlanPolicy::profileForUser($receiver);
        $receiverPlanMaxMb = $receiverProfile['max_upload_size_mb'] ?? null;

        if (! $receiverProfile['subscription'] || ! is_numeric($receiverPlanMaxMb) || (int) $receiverPlanMaxMb < 1) {
            return $senderMaxMb;
        }

        return min($systemMaxMb, max($senderMaxMb, (int) $receiverPlanMaxMb));
    }

    private function receiverSendCapabilities(array $users, ?int $incomingSize = null): array
    {
        if ($users === []) {
            return $this->emptyReceiverSendCapabilities();
        }

        if (count($users) !== 1) {
            $supportsPersonalStorage = collect($users)->every(function (User $user): bool {
                return (bool) (PlanPolicy::profileForUser($user)['allow_personal_storage'] ?? false);
            });

            return [
                'allow_personal_storage' => $supportsPersonalStorage,
                'allow_never_expire' => false,
                'allow_note_without_file' => false,
                'store_in_receiver_storage' => false,
                'storage_near_capacity' => false,
                'storage_full' => false,
            ];
        }

        $user = $users[0];
        $planProfile = PlanPolicy::profileForUser($user);
        $storageProfile = PersonalStorageQuota::profileForUser($user);

        if (PersonalStorageQuota::disableNoExpiryReceivingIfUnavailable($user, $storageProfile)) {
            $user->refresh();
            $storageProfile = PersonalStorageQuota::profileForUser($user);
        }

        $supportsPersonalStorage = (bool) ($planProfile['allow_personal_storage'] ?? false);
        $receiverAllowsNoExpiry = (bool) $user->allow_receive_no_expiry;
        $storageFull = $supportsPersonalStorage
            && $storageProfile['quota_bytes'] !== null
            && (int) ($storageProfile['remaining_bytes'] ?? 0) < 1;
        $canFitIncoming = $supportsPersonalStorage
            && ! $storageFull
            && ($incomingSize === null || PersonalStorageQuota::canStoreUpload($user, $incomingSize));

        return [
            'allow_personal_storage' => $supportsPersonalStorage,
            'allow_never_expire' => $canFitIncoming && $receiverAllowsNoExpiry,
            'allow_note_without_file' => $supportsPersonalStorage && ! $storageFull,
            'store_in_receiver_storage' => $canFitIncoming && $receiverAllowsNoExpiry,
            'storage_near_capacity' => $supportsPersonalStorage && ! $storageFull && PersonalStorageQuota::isNearCapacity($user),
            'storage_full' => $storageFull,
            'receiver_prefers_no_expiry' => $receiverAllowsNoExpiry,
        ];
    }

    private function emptyReceiverSendCapabilities(): array
    {
        return [
            'allow_personal_storage' => false,
            'allow_never_expire' => false,
            'allow_note_without_file' => false,
            'store_in_receiver_storage' => false,
            'storage_near_capacity' => false,
            'storage_full' => false,
            'receiver_prefers_no_expiry' => false,
        ];
    }

    private function allowedExpireOptionsForSend(
        array $senderExpireOptions,
        array $receiverCapabilities,
        bool $senderCanNeverExpire = false
    ): array
    {
        $options = collect($senderExpireOptions);

        if (($receiverCapabilities['allow_never_expire'] ?? false) || $senderCanNeverExpire) {
            if (! $options->contains('never')) {
                $options->push('never');
            }
        } else {
            $options = $options->reject(fn (mixed $value) => (string) $value === 'never')->values();
        }

        return $options
            ->map(fn (mixed $value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, PlanPolicy::EXPIRE_OPTION_VALUES, true))
            ->unique()
            ->values()
            ->all();
    }
}

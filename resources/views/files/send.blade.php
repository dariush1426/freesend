@extends('layouts.app')

@section('page_title', __('ui.send.title'))

@section('content')
    @php
        $lockedReceiverName = $prefilledReceiverUser?->full_name ?: $prefilledReceiverUser?->username;
        $hasOldWizardState = $errors->any()
            || old('destination')
            || old('receiver')
            || old('message')
            || old('custom_expires_at')
            || old('download_password')
            || old('public_link_enabled')
            || old('public_link_expires_at')
            || old('public_link_max_downloads')
            || old('uploaded_file_token');
        $wizardExpandedInitially = $hasOldWizardState || $receiverLocked;
        $selectedDestination = ($receiverLocked || ! $personalStorageEnabled)
            ? 'send'
            : old('destination', $defaultDestination);
    @endphp

    <div class="send-page">
    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.send.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.send.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button primary" href="#send-form">{{ __('ui.send.start_send') }}</a>
            <a class="button" href="{{ route('inbox') }}">{{ __('ui.send.review_conversations') }}</a>
            @if($receiverLocked)
                <a class="button" href="{{ route('files.create') }}">{{ __('ui.send.change_receiver') }}</a>
            @endif
        </div>
    </section>

    <section class="wizard-steps">
        <div class="wizard-step">
            <span class="wizard-step-index">1</span>
            <div><strong>{{ __('ui.send.step_recipient') }}</strong></div>
        </div>
        <div class="wizard-step">
            <span class="wizard-step-index">2</span>
            <div><strong>{{ __('ui.send.step_file') }}</strong></div>
        </div>
        <div class="wizard-step">
            <span class="wizard-step-index">3</span>
            <div><strong>{{ __('ui.send.step_settings') }}</strong></div>
        </div>
        <div class="wizard-step">
            <span class="wizard-step-index">4</span>
            <div><strong>{{ __('ui.send.step_submit') }}</strong></div>
        </div>
    </section>

    <section class="messenger-layout three-columns send-wizard-layout">
        <aside class="wizard-sidebar">
            <div class="panel">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 8px;">{{ __('ui.send.summary_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.send.summary_body')])
                </div>

                <div class="list" style="margin-top: 18px;">
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.send.max_size') }}</strong>
                            <div class="muted">{{ __('ui.send.max_size_value', ['size' => $maxFileSizeMb]) }}</div>
                        </div>
                        <span class="badge">{{ __('ui.common.active') }}</span>
                    </div>
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.send.allowed_formats') }}</strong>
                            <div class="muted">{{ $allowedExtensions ?: __('ui.common.all_formats') }}</div>
                        </div>
                        <span class="badge">{{ __('ui.common.settings') }}</span>
                    </div>
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.send.send_state') }}</strong>
                            <div id="send-summary" class="muted">{{ __('ui.send.summary_empty') }}</div>
                        </div>
                        <span class="badge">{{ __('ui.common.live') }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <section class="panel">
            <form id="send-form" method="post" action="{{ route('files.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="uploaded_file_token" name="uploaded_file_token" type="hidden" value="{{ old('uploaded_file_token') }}">
                <input id="destination-hidden" name="destination" type="hidden" value="{{ $selectedDestination }}">

                <div id="receiver-stage" class="section-block send-accordion-stage" data-stage="receiver" style="margin-top: 16px;">
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.send.receiver_stage_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.send.receiver_stage_body')])
                        </div>
                    </div>

                    @if($personalStorageEnabled && !$receiverLocked)
                        <div class="field">
                            <label>{{ __('ui.send.destination') }}</label>
                            <div class="actions send-segmented-control" style="align-items: stretch;">
                                <label class="checkbox-card send-segment-option" style="flex:1;">
                                    <input type="radio" name="destination_selector" value="send" @checked($selectedDestination !== 'storage')>
                                    <span>{{ __('ui.send.destination_send') }}</span>
                                </label>
                                <label class="checkbox-card send-segment-option" style="flex:1;">
                                    <input type="radio" name="destination_selector" value="storage" @checked($selectedDestination === 'storage')>
                                    <span>{{ __('ui.send.destination_storage') }}</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    <div id="receiver-fields" @if($selectedDestination === 'storage') hidden @endif>
                    @if($receiverLocked && $prefilledReceiverUser)
                        <input type="hidden" name="receiver" value="{{ old('receiver', $prefilledReceiverUser->username) }}">
                        <div class="wizard-locked-recipient">
                            <span class="badge">{{ __('ui.send.receiver_locked') }}</span>
                            <div class="thread-main">
                                <span class="avatar">{{ mb_substr($lockedReceiverName, 0, 1) }}</span>
                                <div class="meta-stack">
                                    <strong>{{ $prefilledReceiverUser->username }}</strong>
                                    <span class="muted">{{ $prefilledReceiverUser->full_name ?: __('ui.send.without_full_name') }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="field" style="position: relative; margin-bottom: 0;">
                            <div class="label-with-help">
                                <label for="receiver">{{ __('ui.send.receiver') }}</label>
                                @include('partials.inline-help', ['text' => __('ui.send.receiver_example', ['example' => 'ali, sara@example.com, 0912xxxxxxx'])])
                            </div>
                            <input id="receiver" name="receiver" value="{{ old('receiver', $prefilledReceiver ?? '') }}" placeholder="{{ __('ui.send.receiver_placeholder') }}" autocomplete="off" required>
                            <div id="receiver-result" class="muted" style="margin-top: 8px;"></div>
                            <div id="receiver-card" class="panel conversation-card" style="display:none; margin-top:12px; padding:14px;">
                                <div class="thread-main">
                                    <span id="receiver-avatar" class="avatar">?</span>
                                    <div class="meta-stack">
                                        <strong id="receiver-username">-</strong>
                                        <span id="receiver-name" class="muted"></span>
                                        <span id="receiver-contact" class="muted"></span>
                                    </div>
                                </div>
                            </div>
                            <div id="receiver-suggestions" style="display:none; position:absolute; z-index:20; inset-inline:0; top:100%; margin-top:6px; max-height:260px; overflow:auto;"></div>
                        </div>
                    @endif
                    </div>

                    <div id="receiver-capability-note" class="status" style="margin-top: 12px; margin-bottom: 0;" hidden></div>

                    @if($personalStorageEnabled)
                        <div id="storage-destination-note" class="status" style="margin-top: 12px; margin-bottom: 0;" @if($selectedDestination !== 'storage') hidden @endif>
                            {{ __('ui.send.destination_storage_body') }}
                        </div>
                        <div id="storage-note-hint" class="muted" style="margin-top: 10px;" @if($selectedDestination !== 'storage') hidden @endif>
                            {{ __('ui.send.destination_storage_note_hint') }}
                        </div>
                    @endif

                    <div class="send-stage-actions">
                        <button class="button primary send-stage-next" type="button" data-open-stage="file">{{ __('ui.send.next_upload_file') }}</button>
                    </div>
                </div>

                <div id="file-stage" class="section-block wizard-stage send-accordion-stage" data-stage="file" @unless($wizardExpandedInitially) hidden @endunless>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.send.upload_first_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.send.upload_first_body')])
                        </div>
                    </div>

                    <div id="send-mode-block" class="field" hidden>
                        <label>{{ __('ui.send.send_mode') }}</label>
                        <div class="actions send-segmented-control" style="align-items: stretch;">
                            <label class="checkbox-card send-segment-option" style="flex:1;">
                                <input type="radio" name="send_mode_selector" value="file" checked>
                                <span>{{ __('ui.send.send_mode_file') }}</span>
                            </label>
                            <label class="checkbox-card send-segment-option" style="flex:1;">
                                <input type="radio" name="send_mode_selector" value="note">
                                <span>{{ __('ui.send.send_mode_note') }}</span>
                            </label>
                        </div>
                        <div class="muted" style="margin-top: 8px;">{{ __('ui.send.send_mode_note_hint') }}</div>
                    </div>

                    <div id="file-upload-block">
                        <div id="file-dropzone" class="file-dropzone">
                            <div class="dropzone-copy">
                                <strong>{{ __('ui.send.dropzone_hint') }}</strong>
                                <div class="muted">{{ __('ui.send.dropzone_or') }} {{ __('ui.send.dropzone_pick') }}</div>
                            </div>
                            <span class="dropzone-action">{{ __('ui.send.dropzone_pick') }}</span>
                            <input id="file" name="file" type="file">
                        </div>

                        <div id="file-card" class="panel conversation-card" style="display:none; margin-top:12px; padding:14px;">
                            <div class="thread-main">
                                <span class="avatar">F</span>
                                <div class="meta-stack">
                                    <strong id="file-name">-</strong>
                                    <span id="file-meta" class="muted"></span>
                                </div>
                            </div>
                        </div>

                        <div id="chunk-upload-box" class="panel conversation-card" style="display:none; margin-top:12px; padding:14px;">
                            <strong>{{ __('ui.send.chunk_status') }}</strong>
                            <div id="chunk-upload-status" class="muted" style="margin-top:8px;">{{ __('ui.send.chunk_ready') }}</div>
                            <div style="margin-top:10px; height:12px; background:#e8edf3; border-radius:999px; overflow:hidden;">
                                <div id="chunk-upload-progress" style="width:0%; height:100%; background:linear-gradient(135deg, #0f766e 0%, #149e90 100%);"></div>
                            </div>
                        </div>
                    </div>

                    <div id="note-only-block" class="status" style="margin-bottom: 0;" hidden>
                        {{ __('ui.send.send_mode_note_enabled') }}
                    </div>

                    <div class="send-stage-actions">
                        <button class="button primary send-stage-next" type="button" data-open-stage="timing">{{ __('ui.send.next_note_expiry') }}</button>
                    </div>
                </div>

                <div id="timing-stage" class="section-block wizard-stage send-accordion-stage" data-stage="timing" @unless($wizardExpandedInitially) hidden @endunless>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.send.section_two') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.send.section_two_body')])
                        </div>
                    </div>

                    <div class="field">
                        <label for="message">{{ __('ui.send.message') }}</label>
                        <textarea
                            id="message"
                            name="message"
                            placeholder="{{ $selectedDestination === 'storage' ? __('ui.send.message_placeholder_storage') : __('ui.send.message_placeholder') }}"
                            data-send-placeholder="{{ __('ui.send.message_placeholder') }}"
                            data-storage-placeholder="{{ __('ui.send.message_placeholder_storage') }}"
                        >{{ old('message') }}</textarea>
                    </div>

                    <div id="expiry-settings-grid" class="grid cols-2" @if($selectedDestination === 'storage') hidden @endif>
                        <div class="field">
                            <label for="expire_option">{{ __('ui.send.expire') }}</label>
                            <select id="expire_option" name="expire_option">
                                @foreach($expireOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('expire_option', 'default') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="custom-expire-field" class="field" style="display:none;">
                            <label for="custom_expires_at">{{ __('ui.send.custom_expire') }}</label>
                            <input id="custom_expires_at" name="custom_expires_at" type="datetime-local" value="{{ old('custom_expires_at') }}">
                        </div>
                    </div>

                    @if($personalStorageEnabled)
                        <div id="storage-expiry-note" class="status" style="margin-bottom: 0;" @if($selectedDestination !== 'storage') hidden @endif>
                            {{ __('ui.send.storage_no_expiry_notice') }}
                        </div>
                    @endif

                    <div class="send-stage-actions">
                        <button class="button send-stage-next" type="button" data-open-stage="security">{{ __('ui.send.optional_security') }}</button>
                    </div>
                </div>

                <div id="security-stage" class="section-block wizard-stage send-accordion-stage" data-stage="security" @unless($wizardExpandedInitially) hidden @endunless @if($selectedDestination === 'storage') style="display:none;" @endif>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.send.section_three') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.send.section_three_body')])
                        </div>
                    </div>

                    @if($planProfile['allow_password_protection'] ?? true)
                        <div class="grid cols-2">
                            <div class="field">
                                <label for="download_password">{{ __('ui.send.download_password') }}</label>
                                <input id="download_password" name="download_password" type="password" autocomplete="new-password" placeholder="{{ __('ui.send.download_password_placeholder') }}">
                            </div>
                            <div class="field">
                                <label for="download_password_confirmation">{{ __('ui.send.download_password_confirmation') }}</label>
                                <input id="download_password_confirmation" name="download_password_confirmation" type="password" autocomplete="new-password">
                            </div>
                        </div>
                    @else
                        <div class="status" style="margin-bottom: 12px;">{{ __('ui.send.password_disabled_by_plan') }}</div>
                    @endif

                    @if($publicLinkFeatureEnabled ?? false)
                        <div class="field">
                            <label class="checkbox-card">
                                <input id="public_link_enabled" type="checkbox" name="public_link_enabled" value="1" @checked(old('public_link_enabled'))>
                                <span>{{ __('ui.send.public_link') }}</span>
                            </label>
                        </div>

                        <div id="public-link-fields" class="grid cols-2" style="display:none;">
                            <div class="field">
                                <label for="public_link_expires_at">{{ __('ui.send.public_link_expire') }}</label>
                                <input id="public_link_expires_at" name="public_link_expires_at" type="datetime-local" value="{{ old('public_link_expires_at') }}">
                            </div>
                            <div class="field">
                                <label for="public_link_max_downloads">{{ __('ui.send.public_link_max') }}</label>
                                <input id="public_link_max_downloads" name="public_link_max_downloads" type="number" min="1" max="100000" value="{{ old('public_link_max_downloads') }}">
                            </div>
                        </div>
                    @else
                        <div class="status" style="margin-bottom: 0;">{{ $publicLinkFeatureNotice ?? __('ui.send.public_link_disabled') }}</div>
                    @endif
                </div>

                <div id="submit-stage" class="action-bar wizard-stage" style="margin-top: 18px;" @unless($wizardExpandedInitially) hidden @endunless>
                    <button id="send-submit" class="button primary" type="submit">{{ $selectedDestination === 'storage' ? __('ui.send.save_to_storage') : __('ui.send.send_file') }}</button>
                    <a class="button" href="{{ route('inbox') }}">{{ __('ui.send.view_conversations') }}</a>
                </div>
            </form>
        </section>
    </section>
    </div>

    <script>
        (() => {
            const sendForm = document.getElementById('send-form');
            if (!sendForm) {
                return;
            }

            const fileInput = document.getElementById('file');
            const fileDropzone = document.getElementById('file-dropzone');
            const fileCard = document.getElementById('file-card');
            const fileName = document.getElementById('file-name');
            const fileMeta = document.getElementById('file-meta');
            const uploadedFileTokenInput = document.getElementById('uploaded_file_token');
            const chunkUploadBox = document.getElementById('chunk-upload-box');
            const chunkUploadStatus = document.getElementById('chunk-upload-status');
            const chunkUploadProgress = document.getElementById('chunk-upload-progress');
            const sendSummary = document.getElementById('send-summary');
            const expireOptionInput = document.getElementById('expire_option');
            const customExpireField = document.getElementById('custom-expire-field');
            const customExpireInput = document.getElementById('custom_expires_at');
            const downloadPasswordInput = document.getElementById('download_password');
            const publicLinkEnabledInput = document.getElementById('public_link_enabled');
            const publicLinkFields = document.getElementById('public-link-fields');
            const sendSubmit = document.getElementById('send-submit');
            const submitStage = document.getElementById('submit-stage');
            const wizardStages = Array.from(document.querySelectorAll('.wizard-stage'));
            const receiverInput = document.getElementById('receiver');
            const receiverResult = document.getElementById('receiver-result');
            const receiverSuggestions = document.getElementById('receiver-suggestions');
            const receiverCard = document.getElementById('receiver-card');
            const receiverAvatar = document.getElementById('receiver-avatar');
            const receiverUsername = document.getElementById('receiver-username');
            const receiverName = document.getElementById('receiver-name');
            const receiverContact = document.getElementById('receiver-contact');
            const hiddenReceiverInput = sendForm.querySelector('input[name="receiver"][type="hidden"]');
            const destinationHiddenInput = document.getElementById('destination-hidden');
            const destinationSelectorInputs = Array.from(sendForm.querySelectorAll('input[name="destination_selector"]'));
            const receiverFields = document.getElementById('receiver-fields');
            const receiverCapabilityNote = document.getElementById('receiver-capability-note');
            const storageDestinationNote = document.getElementById('storage-destination-note');
            const storageNoteHint = document.getElementById('storage-note-hint');
            const fileStage = document.getElementById('file-stage');
            const fileUploadBlock = document.getElementById('file-upload-block');
            const sendModeBlock = document.getElementById('send-mode-block');
            const noteOnlyBlock = document.getElementById('note-only-block');
            const sendModeInputs = Array.from(sendForm.querySelectorAll('input[name="send_mode_selector"]'));
            const expirySettingsGrid = document.getElementById('expiry-settings-grid');
            const storageExpiryNote = document.getElementById('storage-expiry-note');
            const securityStage = document.getElementById('security-stage');
            const messageInput = document.getElementById('message');
            const sendStages = {
                receiver: document.getElementById('receiver-stage'),
                file: document.getElementById('file-stage'),
                timing: document.getElementById('timing-stage'),
                security: document.getElementById('security-stage'),
            };
            const stageToggleButtons = Array.from(sendForm.querySelectorAll('[data-stage-toggle]'));
            const stageNextButtons = Array.from(sendForm.querySelectorAll('[data-open-stage]'));
            const stageHeadings = Array.from(sendForm.querySelectorAll('.send-accordion-stage > .section-heading'));

            const routes = {
                chunkStart: @json(route('uploads.chunk.start', [], false)),
                chunkStatus: @json(route('uploads.chunk.status', [], false)),
                chunkPart: @json(route('uploads.chunk.part', [], false)),
                chunkFinish: @json(route('uploads.chunk.finish', [], false)),
                usersLookup: @json(route('users.lookup', [], false)),
            };

            const receiverLocked = @json($receiverLocked);
            const prefilledReceiverCapabilities = @json($prefilledReceiverCapabilities);
            const shouldRevealInitially = @json((bool) $wizardExpandedInitially);
            const csrfToken = @json(csrf_token());
            const configuredChunkSizeBytes = Number(@json($chunkUploadSizeBytes));
            const chunkSizeBytes = Math.max(256 * 1024, Math.min(configuredChunkSizeBytes || (2 * 1024 * 1024), 5 * 1024 * 1024));
            const storageKeyPrefix = 'freesend:chunk-upload:';
            const senderExpireOptionValues = @json($senderExpireOptionValues);
            const allExpireOptions = @json($allExpireOptions);

            const summaryReceiverTemplate = @json(__('ui.send.summary_receiver'));
            const summaryDestinationTemplate = @json(__('ui.send.summary_destination'));
            const summaryFileTemplate = @json(__('ui.send.summary_file'));
            const summaryExpireTemplate = @json(__('ui.send.summary_expire'));
            const receiverAddedTemplate = @json(__('ui.send.receiver_added'));
            const receiverSearchFoundTemplate = @json(__('ui.send.receiver_search_found'));
            const resumeUploadTemplate = @json(__('ui.send.resume_upload'));
            const uploadPartTemplate = @json(__('ui.send.upload_part'));
            const fallbackWithoutFullName = @json(__('ui.send.without_full_name'));

            const translations = {
                summaryEmpty: @json(__('ui.send.summary_empty')),
                summaryReceiverNone: @json(__('ui.send.summary_receiver_none')),
                summaryFileNone: @json(__('ui.send.summary_file_none')),
                summaryPasswordOn: @json(__('ui.send.summary_password_on')),
                summaryPasswordOff: @json(__('ui.send.summary_password_off')),
                summaryPublicOn: @json(__('ui.send.summary_public_on')),
                summaryPublicOff: @json(__('ui.send.summary_public_off')),
                summaryStorageDestination: @json(__('ui.send.summary_storage_destination')),
                summaryNoteOnly: @json(__('ui.send.summary_note_only')),
                receiverSearchNone: @json(__('ui.send.receiver_search_none')),
                receiverSearchError: @json(__('ui.send.receiver_search_error')),
                receiverAlreadySelected: @json(__('ui.send.receiver_already_selected')),
                receiverStorageEnabled: @json(__('ui.send.receiver_storage_enabled')),
                receiverStorageNoteOnly: @json(__('ui.send.receiver_storage_note_only')),
                receiverStorageNearCapacity: @json(__('ui.send.receiver_storage_near_capacity')),
                receiverStorageFull: @json(__('ui.send.receiver_storage_full')),
                receiverStorageDisabled: @json(__('ui.send.receiver_storage_disabled')),
                sendModeNoteEnabled: @json(__('ui.send.send_mode_note_enabled')),
                chunkReady: @json(__('ui.send.chunk_ready')),
                uploadReady: @json(__('ui.send.upload_ready')),
                requestFailed: @json(__('ui.send.request_failed')),
                networkFailed: @json(__('ui.send.network_failed')),
                unknownType: @json(__('ui.send.unknown_type')),
                prepareUpload: @json(__('ui.send.prepare_upload')),
                uploadComplete: @json(__('ui.send.upload_complete')),
                uploadFinalize: @json(__('ui.send.upload_finalize')),
                chunkErrorPrefix: @json(__('ui.send.chunk_error_prefix')),
                sendFile: @json(__('ui.send.send_file')),
                saveToStorage: @json(__('ui.send.save_to_storage')),
            };

            let chunkUploading = false;
            let currentUploadPromise = null;
            let lookupTimer = null;
            let lookupAbortController = null;
            let programmaticSubmit = false;
            let currentReceiverCapabilities = receiverLocked ? prefilledReceiverCapabilities : null;
            let confirmedReceiverValue = receiverLocked ? (hiddenReceiverInput?.value.trim() || '') : '';
            let manuallyOpenedSecurity = Boolean(@json($errors->any() || old('download_password') || old('public_link_enabled') || old('public_link_expires_at') || old('public_link_max_downloads')));
            const openedStages = {
                file: Boolean(@json($wizardExpandedInitially && $receiverLocked)),
                timing: Boolean(@json($errors->any() || old('message') || old('custom_expires_at') || old('uploaded_file_token'))),
            };

            const interpolate = (template, replacements) => {
                return Object.entries(replacements).reduce((value, [key, replacement]) => {
                    return value.replace(new RegExp(':' + key, 'g'), String(replacement));
                }, template);
            };

            const safeLocalStorageGet = (key) => {
                try {
                    return window.localStorage.getItem(key);
                } catch (error) {
                    return null;
                }
            };

            const safeLocalStorageSet = (key, value) => {
                try {
                    window.localStorage.setItem(key, value);
                } catch (error) {
                    // ignore storage write issues
                }
            };

            const safeLocalStorageRemove = (key) => {
                try {
                    window.localStorage.removeItem(key);
                } catch (error) {
                    // ignore storage remove issues
                }
            };

            const getDestinationValue = () => {
                return destinationHiddenInput?.value === 'storage' ? 'storage' : 'send';
            };

            const getSendModeValue = () => {
                const selectedInput = sendModeInputs.find((input) => input.checked);
                return selectedInput?.value === 'note' ? 'note' : 'file';
            };

            const normalizedReceiverCapabilities = (capabilities) => ({
                allow_personal_storage: Boolean(capabilities?.allow_personal_storage),
                allow_never_expire: Boolean(capabilities?.allow_never_expire),
                allow_note_without_file: Boolean(capabilities?.allow_note_without_file),
                storage_near_capacity: Boolean(capabilities?.storage_near_capacity),
                storage_full: Boolean(capabilities?.storage_full),
                receiver_prefers_no_expiry: Boolean(capabilities?.receiver_prefers_no_expiry),
            });

            const currentSendCapabilities = () => {
                if (getDestinationValue() === 'storage') {
                    return {
                        allow_note_without_file: true,
                        allow_never_expire: true,
                    };
                }

                return normalizedReceiverCapabilities(currentReceiverCapabilities);
            };

            const getReceiverValue = () => {
                if (getDestinationValue() === 'storage') {
                    return translations.summaryStorageDestination;
                }

                if (receiverLocked) {
                    return hiddenReceiverInput?.value.trim() || '';
                }

                return receiverInput?.value.trim() || '';
            };

            const getLookupQuery = (rawValue) => {
                const source = String(rawValue || '');
                const parts = source.split(/[;,]/);
                return (parts[parts.length - 1] || '').trim();
            };

            const replaceLookupQuery = (rawValue, replacement) => {
                const value = String(rawValue || '');
                const separatorIndex = Math.max(value.lastIndexOf(','), value.lastIndexOf(';'));

                if (separatorIndex === -1) {
                    return replacement;
                }

                return value.slice(0, separatorIndex + 1) + ' ' + replacement;
            };

            const normalizeLookupValue = (value) => String(value || '').trim().toLowerCase();
            const selectedReceiverTokens = () => {
                const value = receiverInput?.value || '';
                const separatorIndex = Math.max(value.lastIndexOf(','), value.lastIndexOf(';'));

                if (separatorIndex === -1) {
                    return new Set();
                }

                return new Set(
                    value
                        .slice(0, separatorIndex)
                        .split(/[\s,;]+/u)
                        .map((token) => normalizeLookupValue(token))
                        .filter(Boolean)
                );
            };

            const isReceiverAlreadySelected = (user, selectedTokens) => {
                return [user.username, user.email, user.mobile]
                    .map((value) => normalizeLookupValue(value))
                    .filter(Boolean)
                    .some((value) => selectedTokens.has(value));
            };

            const findExactReceiverMatch = (users, query) => {
                if (!Array.isArray(users) || users.length === 0) {
                    return null;
                }

                const normalizedQuery = normalizeLookupValue(query);
                if (!normalizedQuery) {
                    return null;
                }

                return users.find((user) => {
                    const usernameMatches = normalizeLookupValue(user.username) === normalizedQuery;
                    const emailMatches = normalizeLookupValue(user.email) === normalizedQuery;
                    const mobileMatches = normalizeLookupValue(user.mobile) === normalizedQuery;

                    return usernameMatches || emailMatches || mobileMatches;
                }) || null;
            };

            const getUploadStorageKey = (file) => {
                return storageKeyPrefix + [file.name, file.size, file.lastModified].join(':');
            };

            const formatBytes = (bytes) => {
                const size = Number(bytes) || 0;
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                let unitIndex = 0;
                let value = size;

                while (value >= 1024 && unitIndex < units.length - 1) {
                    value /= 1024;
                    unitIndex += 1;
                }

                const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined, {
                    maximumFractionDigits: unitIndex === 0 ? 0 : 1,
                });

                return formatter.format(value) + ' ' + units[unitIndex];
            };

            const setChunkStatus = (text, percent = 0) => {
                chunkUploadBox.style.display = 'block';
                chunkUploadStatus.textContent = text;
                chunkUploadProgress.style.width = Math.max(0, Math.min(percent, 100)) + '%';
            };

            const toggleCustomExpire = () => {
                const visible = expireOptionInput?.value === 'custom';
                if (customExpireField) {
                    customExpireField.style.display = visible ? '' : 'none';
                }
                if (customExpireInput) {
                    customExpireInput.required = visible;
                }
            };

            const togglePublicLinkFields = () => {
                if (!publicLinkEnabledInput || !publicLinkFields) {
                    return;
                }

                publicLinkFields.style.display = publicLinkEnabledInput.checked ? '' : 'none';
            };

            const allowedExpireOptionValues = () => {
                const values = [...senderExpireOptionValues];

                if (normalizedReceiverCapabilities(currentReceiverCapabilities).allow_never_expire && !values.includes('never')) {
                    values.push('never');
                }

                return values.filter((value) => Object.prototype.hasOwnProperty.call(allExpireOptions, value));
            };

            const rebuildExpireOptions = () => {
                if (!expireOptionInput) {
                    return;
                }

                const currentValue = expireOptionInput.value || 'default';
                const allowedValues = allowedExpireOptionValues();

                expireOptionInput.innerHTML = '';

                allowedValues.forEach((value) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = allExpireOptions[value] || value;
                    expireOptionInput.appendChild(option);
                });

                expireOptionInput.value = allowedValues.includes(currentValue) ? currentValue : (allowedValues[0] || 'default');
                toggleCustomExpire();
            };

            const renderReceiverCapabilityNote = () => {
                if (!receiverCapabilityNote || getDestinationValue() === 'storage') {
                    if (receiverCapabilityNote) {
                        receiverCapabilityNote.hidden = true;
                    }
                    return;
                }

                const receiverValue = receiverLocked
                    ? (hiddenReceiverInput?.value.trim() || '')
                    : (receiverInput?.value.trim() || '');

                if (!receiverValue) {
                    receiverCapabilityNote.hidden = true;
                    return;
                }

                const capabilities = normalizedReceiverCapabilities(currentReceiverCapabilities);
                if (capabilities.storage_full) {
                    receiverCapabilityNote.textContent = translations.receiverStorageFull;
                } else if (capabilities.storage_near_capacity) {
                    receiverCapabilityNote.textContent = translations.receiverStorageNearCapacity;
                } else if (capabilities.allow_note_without_file && !capabilities.allow_never_expire) {
                    receiverCapabilityNote.textContent = translations.receiverStorageNoteOnly;
                } else {
                    receiverCapabilityNote.textContent = capabilities.allow_note_without_file
                        ? translations.receiverStorageEnabled
                        : translations.receiverStorageDisabled;
                }
                receiverCapabilityNote.hidden = false;
            };

            const resetSelectedFile = () => {
                if (fileInput) {
                    fileInput.value = '';
                }

                if (uploadedFileTokenInput) {
                    uploadedFileTokenInput.value = '';
                }

                if (fileCard) {
                    fileCard.style.display = 'none';
                }

                if (fileDropzone) {
                    fileDropzone.classList.remove('has-file');
                }

                if (chunkUploadBox) {
                    chunkUploadBox.style.display = 'none';
                }
            };

            const toggleSendModeState = () => {
                const destinationIsStorage = getDestinationValue() === 'storage';
                const capabilities = currentSendCapabilities();
                const canSendNoteWithoutFile = Boolean(capabilities.allow_note_without_file);
                const showSendMode = !destinationIsStorage && canSendNoteWithoutFile;
                const noteMode = showSendMode && getSendModeValue() === 'note';

                if (sendModeBlock) {
                    sendModeBlock.hidden = !showSendMode;
                }

                if (!showSendMode) {
                    sendModeInputs.forEach((input) => {
                        input.checked = input.value === 'file';
                    });
                }

                if (fileStage) {
                    fileStage.hidden = !isCompactSendUi() && !destinationIsStorage && !showSendMode && !receiverLocked && !getReceiverValue();
                }

                if (fileUploadBlock) {
                    fileUploadBlock.hidden = noteMode;
                }

                if (noteOnlyBlock) {
                    noteOnlyBlock.hidden = !noteMode;
                    noteOnlyBlock.textContent = translations.sendModeNoteEnabled;
                }

                if (noteMode) {
                    resetSelectedFile();
                }
            };

            const toggleDestinationState = () => {
                const isStorage = getDestinationValue() === 'storage';

                if (receiverFields) {
                    receiverFields.hidden = isStorage;
                }

                if (storageDestinationNote) {
                    storageDestinationNote.hidden = !isStorage;
                }

                if (storageNoteHint) {
                    storageNoteHint.hidden = !isStorage;
                }

                if (expirySettingsGrid) {
                    expirySettingsGrid.hidden = isStorage;
                }

                if (storageExpiryNote) {
                    storageExpiryNote.hidden = !isStorage;
                }

                if (receiverInput) {
                    receiverInput.required = !isStorage;
                }

                if (fileInput) {
                    fileInput.required = false;
                }

                if (messageInput) {
                    messageInput.placeholder = isStorage
                        ? (messageInput.dataset.storagePlaceholder || messageInput.placeholder)
                        : (messageInput.dataset.sendPlaceholder || messageInput.placeholder);
                }

                if (securityStage) {
                    securityStage.style.display = isStorage ? 'none' : '';
                }

                if (sendSubmit) {
                    sendSubmit.textContent = isStorage ? translations.saveToStorage : translations.sendFile;
                }

                renderReceiverCapabilityNote();
                rebuildExpireOptions();
                toggleSendModeState();
            };

            const isCompactSendUi = () => window.matchMedia('(max-width: 640px)').matches;

            const setStageExpanded = (stageName, expanded) => {
                const stage = sendStages[stageName];

                if (!stage) {
                    return;
                }

                stage.classList.toggle('send-accordion-collapsed', !expanded);

                const toggle = stageToggleButtons.find((button) => button.dataset.stageToggle === stageName);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }
            };

            const openStage = (stageName, shouldScroll = false) => {
                const stage = sendStages[stageName];

                if (!stage) {
                    return;
                }

                stage.hidden = false;
                if (stageName === 'file' || stageName === 'timing') {
                    openedStages[stageName] = true;
                }
                setStageExpanded(stageName, true);

                if (stageName === 'security') {
                    manuallyOpenedSecurity = true;
                }

                if (shouldScroll) {
                    window.setTimeout(() => {
                        stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 40);
                }
            };

            const receiverStepComplete = () => {
                return getDestinationValue() === 'storage'
                    || receiverLocked
                    || Boolean(confirmedReceiverValue);
            };

            const fileStepComplete = () => {
                return getDestinationValue() === 'storage'
                    || getSendModeValue() === 'note'
                    || Boolean(uploadedFileTokenInput.value)
                    || Boolean(fileInput?.files?.[0]);
            };

            const syncSendAccordion = () => {
                const compact = isCompactSendUi();
                const receiverComplete = receiverStepComplete();
                const fileComplete = fileStepComplete();
                const fileVisible = receiverComplete && (openedStages.file || fileComplete);
                const timingVisible = fileComplete && (openedStages.timing || fileComplete);

                if (sendStages.file) {
                    sendStages.file.hidden = !fileVisible;
                }

                if (sendStages.timing) {
                    sendStages.timing.hidden = !timingVisible;
                }

                if (sendStages.security && getDestinationValue() !== 'storage') {
                    sendStages.security.hidden = !manuallyOpenedSecurity;
                }

                setStageExpanded('receiver', compact ? !receiverComplete : true);
                setStageExpanded('file', fileVisible && !fileComplete);
                setStageExpanded('timing', timingVisible);
                setStageExpanded('security', compact ? manuallyOpenedSecurity : manuallyOpenedSecurity || Boolean(shouldRevealInitially));
            };

            const syncStageActionButtons = () => {
                stageNextButtons.forEach((button) => {
                    const target = button.dataset.openStage;
                    const disabled = (target === 'file' && !receiverStepComplete())
                        || (target === 'timing' && !fileStepComplete());

                    button.disabled = disabled;
                });
            };

            const setWizardExpanded = (expanded) => {
                wizardStages.forEach((stage) => {
                    if (!expanded && !isCompactSendUi()) {
                        stage.hidden = true;
                    }
                });
            };

            const syncWizardVisibility = () => {
                const receiverValue = receiverLocked
                    ? (hiddenReceiverInput?.value.trim() || '')
                    : (receiverInput?.value.trim() || '');
                const shouldExpand = isCompactSendUi()
                    || shouldRevealInitially
                    || Boolean(uploadedFileTokenInput.value)
                    || Boolean(fileInput?.files?.[0])
                    || Boolean(receiverValue)
                    || Boolean(messageInput?.value.trim())
                    || getDestinationValue() === 'storage';

                setWizardExpanded(shouldExpand);
                toggleSendModeState();
                syncSendAccordion();
                syncStageActionButtons();

                if (submitStage) {
                    submitStage.hidden = !(receiverStepComplete() && fileStepComplete());
                }
            };

            window.addEventListener('resize', syncWizardVisibility);

            const updateSummary = () => {
                const receiverValue = getReceiverValue();
                const selectedFile = fileInput?.files?.[0] || null;

                if (!receiverValue && !selectedFile && !uploadedFileTokenInput.value) {
                    sendSummary.textContent = translations.summaryEmpty;
                    return;
                }

                const receiverSummary = receiverValue
                    ? interpolate(getDestinationValue() === 'storage' ? summaryDestinationTemplate : summaryReceiverTemplate, { value: receiverValue })
                    : translations.summaryReceiverNone;

                const noteOnlyActive = getDestinationValue() === 'storage'
                    || (currentSendCapabilities().allow_note_without_file && getSendModeValue() === 'note');

                const fileSummary = selectedFile
                    ? interpolate(summaryFileTemplate, { value: selectedFile.name })
                    : (noteOnlyActive && messageInput?.value.trim()
                        ? translations.summaryNoteOnly
                        : translations.summaryFileNone);

                const expireLabel = getDestinationValue() === 'storage'
                    ? @json(__('ui.send.never_expire'))
                    : (expireOptionInput?.selectedOptions?.[0]?.textContent?.trim() || '');
                const expireSummary = expireLabel
                    ? interpolate(summaryExpireTemplate, { value: expireLabel })
                    : '';

                const summaryParts = [
                    receiverSummary,
                    fileSummary,
                    expireSummary,
                    getDestinationValue() === 'storage'
                        ? ''
                        : (downloadPasswordInput?.value ? translations.summaryPasswordOn : translations.summaryPasswordOff),
                ];

                if (publicLinkEnabledInput && getDestinationValue() !== 'storage') {
                    summaryParts.push(publicLinkEnabledInput.checked ? translations.summaryPublicOn : translations.summaryPublicOff);
                }

                sendSummary.textContent = summaryParts.filter(Boolean).join(' | ');
            };

            const closeSuggestions = () => {
                if (!receiverSuggestions) {
                    return;
                }

                receiverSuggestions.innerHTML = '';
                receiverSuggestions.style.display = 'none';
            };

            const fillReceiverCard = (user) => {
                if (!receiverCard || !receiverAvatar || !receiverUsername || !receiverName || !receiverContact) {
                    return;
                }

                if (!user) {
                    receiverCard.style.display = 'none';
                    receiverUsername.textContent = '-';
                    receiverName.textContent = '';
                    receiverContact.textContent = '';
                    receiverAvatar.textContent = '?';
                    currentReceiverCapabilities = null;
                    confirmedReceiverValue = receiverLocked ? (hiddenReceiverInput?.value.trim() || '') : '';
                    toggleSendModeState();
                    return;
                }

                const identity = user.full_name || user.username || '?';
                const contacts = [user.email, user.mobile].filter(Boolean).join(' | ');

                receiverAvatar.textContent = identity.charAt(0).toUpperCase();
                receiverUsername.textContent = user.username;
                receiverName.textContent = user.full_name || fallbackWithoutFullName;
                receiverContact.textContent = contacts;
                receiverCard.style.display = 'block';
                currentReceiverCapabilities = normalizedReceiverCapabilities(user.capabilities);
                confirmedReceiverValue = user.username || getLookupQuery(receiverInput?.value || '');
                renderReceiverCapabilityNote();
                rebuildExpireOptions();
                syncWizardVisibility();
            };

            const renderSuggestions = (users) => {
                if (!receiverSuggestions) {
                    return;
                }

                if (!Array.isArray(users) || users.length === 0) {
                    closeSuggestions();
                    return;
                }

                receiverSuggestions.innerHTML = '';
                const selectedTokens = selectedReceiverTokens();

                users.forEach((user) => {
                    const alreadySelected = isReceiverAlreadySelected(user, selectedTokens);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'panel conversation-card';
                    button.style.width = '100%';
                    button.style.padding = '12px 14px';
                    button.style.marginBottom = '6px';
                    button.style.cursor = alreadySelected ? 'not-allowed' : 'pointer';
                    button.style.textAlign = 'start';
                    button.disabled = alreadySelected;
                    button.classList.toggle('receiver-suggestion-disabled', alreadySelected);
                    const threadMain = document.createElement('div');
                    threadMain.className = 'thread-main';

                    const avatar = document.createElement('span');
                    avatar.className = 'avatar';
                    avatar.textContent = (user.full_name || user.username || '?').charAt(0).toUpperCase();

                    const metaStack = document.createElement('div');
                    metaStack.className = 'meta-stack';

                    const username = document.createElement('strong');
                    username.textContent = user.username;

                    const fullName = document.createElement('span');
                    fullName.className = 'muted';
                    fullName.textContent = user.full_name || fallbackWithoutFullName;

                    const contact = document.createElement('span');
                    contact.className = 'muted';
                    contact.textContent = [user.email, user.mobile].filter(Boolean).join(' | ');

                    metaStack.appendChild(username);
                    metaStack.appendChild(fullName);
                    metaStack.appendChild(contact);

                    if (alreadySelected) {
                        const selectedHint = document.createElement('span');
                        selectedHint.className = 'badge';
                        selectedHint.textContent = translations.receiverAlreadySelected;
                        metaStack.appendChild(selectedHint);
                    }

                    threadMain.appendChild(avatar);
                    threadMain.appendChild(metaStack);
                    button.appendChild(threadMain);

                    button.addEventListener('click', () => {
                        if (!receiverInput) {
                            return;
                        }

                        receiverInput.value = replaceLookupQuery(receiverInput.value, user.username);
                        receiverResult.textContent = interpolate(receiverAddedTemplate, { user: user.username });
                        fillReceiverCard(user);
                        closeSuggestions();
                        updateSummary();
                        syncWizardVisibility();
                        receiverInput.focus();
                    });

                    receiverSuggestions.appendChild(button);
                });

                receiverSuggestions.style.display = 'block';
            };

            const postFormData = async (url, formData) => {
                let response;

                try {
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                } catch (error) {
                    throw new Error(translations.networkFailed);
                }

                let payload = {};

                try {
                    payload = await response.json();
                } catch (error) {
                    payload = {};
                }

                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || translations.requestFailed);
                }

                return payload;
            };

            const startOrResumeUpload = async (file, totalChunks) => {
                const storageKey = getUploadStorageKey(file);
                const rawStored = safeLocalStorageGet(storageKey);

                if (rawStored) {
                    try {
                        const stored = JSON.parse(rawStored);

                        if (stored?.uploadId && Number(stored.totalChunks) === totalChunks) {
                            const statusFormData = new FormData();
                            statusFormData.append('upload_id', stored.uploadId);

                            const statusPayload = await postFormData(routes.chunkStatus, statusFormData);
                            const receivedChunks = Array.isArray(statusPayload.received_chunks) ? statusPayload.received_chunks : [];

                            return {
                                uploadId: statusPayload.upload_id,
                                receivedChunks: new Set(receivedChunks.map((value) => Number(value))),
                                storageKey,
                            };
                        }
                    } catch (error) {
                        safeLocalStorageRemove(storageKey);
                    }
                }

                const startFormData = new FormData();
                startFormData.append('original_name', file.name);
                startFormData.append('size', String(file.size));
                startFormData.append('mime_type', file.type || '');
                startFormData.append('total_chunks', String(totalChunks));

                const startPayload = await postFormData(routes.chunkStart, startFormData);

                safeLocalStorageSet(storageKey, JSON.stringify({
                    uploadId: startPayload.upload_id,
                    totalChunks,
                }));

                return {
                    uploadId: startPayload.upload_id,
                    receivedChunks: new Set(),
                    storageKey,
                };
            };

            const uploadFileInChunks = async (file) => {
                const totalChunks = Math.max(1, Math.ceil(file.size / chunkSizeBytes));
                const { uploadId, receivedChunks, storageKey } = await startOrResumeUpload(file, totalChunks);

                if (receivedChunks.size > 0) {
                    setChunkStatus(interpolate(resumeUploadTemplate, {
                        done: receivedChunks.size,
                        total: totalChunks,
                    }), Math.round((receivedChunks.size / totalChunks) * 100));
                }

                for (let index = 0; index < totalChunks; index += 1) {
                    if (receivedChunks.has(index)) {
                        continue;
                    }

                    const start = index * chunkSizeBytes;
                    const end = Math.min(file.size, start + chunkSizeBytes);
                    const chunkBlob = file.slice(start, end);
                    const chunkFormData = new FormData();

                    chunkFormData.append('upload_id', uploadId);
                    chunkFormData.append('chunk_index', String(index));
                    chunkFormData.append('total_chunks', String(totalChunks));
                    chunkFormData.append('chunk', chunkBlob, file.name);

                    await postFormData(routes.chunkPart, chunkFormData);

                    receivedChunks.add(index);
                    setChunkStatus(interpolate(uploadPartTemplate, {
                        done: receivedChunks.size,
                        total: totalChunks,
                    }), Math.round((receivedChunks.size / totalChunks) * 100));
                }

                setChunkStatus(translations.uploadComplete, 100);

                const finishFormData = new FormData();
                finishFormData.append('upload_id', uploadId);

                const finishPayload = await postFormData(routes.chunkFinish, finishFormData);
                safeLocalStorageRemove(storageKey);

                return finishPayload.uploaded_file_token || '';
            };

            const uploadSelectedFile = async () => {
                const selectedFile = fileInput?.files?.[0];

                if (!selectedFile) {
                    return '';
                }

                if (chunkUploading && currentUploadPromise) {
                    return currentUploadPromise;
                }

                uploadedFileTokenInput.value = '';
                fileInput.required = false;
                fileInput.disabled = true;
                sendSubmit.disabled = true;
                setChunkStatus(translations.prepareUpload, 0);

                chunkUploading = true;
                currentUploadPromise = uploadFileInChunks(selectedFile)
                    .then((token) => {
                        if (getSendModeValue() === 'note') {
                            uploadedFileTokenInput.value = '';
                            chunkUploadBox.style.display = 'none';
                            return '';
                        }

                        uploadedFileTokenInput.value = token;
                        setChunkStatus(translations.uploadReady, 100);
                        return token;
                    })
                    .catch((error) => {
                        const message = error instanceof Error ? error.message : String(error);
                        setChunkStatus(translations.chunkErrorPrefix + ' ' + message, 0);
                        return '';
                    })
                    .finally(() => {
                        chunkUploading = false;
                        currentUploadPromise = null;
                        fileInput.disabled = false;
                        sendSubmit.disabled = false;
                        updateSummary();
                    });

                return currentUploadPromise;
            };

            const showFileState = () => {
                const selectedFile = fileInput?.files?.[0];

                if (!selectedFile) {
                    fileCard.style.display = 'none';
                    fileDropzone.classList.remove('has-file');
                    uploadedFileTokenInput.value = '';
                    chunkUploadBox.style.display = 'none';
                    updateSummary();
                    syncWizardVisibility();
                    return;
                }

                const fileType = selectedFile.type || translations.unknownType;
                fileName.textContent = selectedFile.name;
                fileMeta.textContent = formatBytes(selectedFile.size) + ' | ' + fileType;
                fileCard.style.display = 'block';
                fileDropzone.classList.add('has-file');
                setChunkStatus(translations.prepareUpload, 0);
                openedStages.timing = true;
                updateSummary();
                syncWizardVisibility();
                uploadSelectedFile();
            };
            const lookupReceiver = () => {
                if (!receiverInput || !receiverSuggestions || !receiverResult) {
                    return;
                }

                const query = getLookupQuery(receiverInput.value);
                confirmedReceiverValue = '';
                updateSummary();

                if (lookupAbortController) {
                    lookupAbortController.abort();
                }

                clearTimeout(lookupTimer);

                if (query.length < 2) {
                    receiverResult.textContent = '';
                    closeSuggestions();
                    currentReceiverCapabilities = null;
                    renderReceiverCapabilityNote();
                    rebuildExpireOptions();
                    fillReceiverCard(null);
                    syncWizardVisibility();
                    return;
                }

                lookupTimer = window.setTimeout(async () => {
                    lookupAbortController = new AbortController();

                    try {
                        const response = await fetch(routes.usersLookup + '?q=' + encodeURIComponent(query), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: lookupAbortController.signal,
                        });

                        const payload = await response.json();
                        const users = Array.isArray(payload.users) ? payload.users : [];

                        if (!payload.found || users.length === 0) {
                            receiverResult.textContent = translations.receiverSearchNone;
                            currentReceiverCapabilities = null;
                            fillReceiverCard(null);
                            closeSuggestions();
                            renderReceiverCapabilityNote();
                            rebuildExpireOptions();
                            syncWizardVisibility();
                            return;
                        }

                        receiverResult.textContent = interpolate(receiverSearchFoundTemplate, { count: users.length });
                        renderSuggestions(users);

                        const exactUser = findExactReceiverMatch(users, query);
                        currentReceiverCapabilities = exactUser ? normalizedReceiverCapabilities(exactUser.capabilities) : null;
                        renderReceiverCapabilityNote();
                        rebuildExpireOptions();
                        fillReceiverCard(exactUser || null);
                        syncWizardVisibility();
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        receiverResult.textContent = translations.receiverSearchError;
                        currentReceiverCapabilities = null;
                        renderReceiverCapabilityNote();
                        rebuildExpireOptions();
                        closeSuggestions();
                    }
                }, 180);
            };

            fileInput?.addEventListener('change', showFileState);

            fileDropzone?.addEventListener('click', (event) => {
                if (event.target === fileInput) {
                    return;
                }

                fileInput?.click();
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                fileDropzone?.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    fileDropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
                fileDropzone?.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    fileDropzone.classList.remove('dragover');
                });
            });

            fileDropzone?.addEventListener('drop', (event) => {
                const droppedFiles = event.dataTransfer?.files;

                if (!droppedFiles || droppedFiles.length === 0 || !fileInput || fileInput.disabled) {
                    return;
                }

                fileInput.files = droppedFiles;
                showFileState();
            });

            receiverInput?.addEventListener('input', lookupReceiver);
            receiverInput?.addEventListener('input', syncWizardVisibility);
            sendModeInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    toggleSendModeState();
                    updateSummary();
                    syncWizardVisibility();
                });
            });
            destinationSelectorInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    if (destinationHiddenInput) {
                        destinationHiddenInput.value = input.value === 'storage' ? 'storage' : 'send';
                    }

                    toggleDestinationState();
                    syncWizardVisibility();
                    updateSummary();
                });
            });

            document.addEventListener('click', (event) => {
                if (!receiverSuggestions || !receiverInput) {
                    return;
                }

                if (receiverSuggestions.contains(event.target) || receiverInput.contains(event.target)) {
                    return;
                }

                closeSuggestions();
            });

            [expireOptionInput, customExpireInput, downloadPasswordInput].forEach((element) => {
                element?.addEventListener('input', updateSummary);
                element?.addEventListener('change', updateSummary);
            });

            messageInput?.addEventListener('input', updateSummary);
            messageInput?.addEventListener('change', updateSummary);
            messageInput?.addEventListener('input', syncWizardVisibility);
            messageInput?.addEventListener('change', syncWizardVisibility);

            expireOptionInput?.addEventListener('change', () => {
                toggleCustomExpire();
                updateSummary();
            });

            publicLinkEnabledInput?.addEventListener('change', () => {
                togglePublicLinkFields();
                updateSummary();
            });

            stageToggleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const stageName = button.dataset.stageToggle;
                    const stage = sendStages[stageName];

                    if (!stage) {
                        return;
                    }

                    const isOpen = !stage.classList.contains('send-accordion-collapsed');
                    if (isOpen && stageName !== 'receiver') {
                        if (stageName === 'security') {
                            manuallyOpenedSecurity = false;
                        }
                        setStageExpanded(stageName, false);
                        return;
                    }

                    openStage(stageName, false);
                });
            });

            stageNextButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const stageName = button.dataset.openStage;

                    if (button.disabled) {
                        if (stageName === 'file') {
                            receiverInput?.focus();
                        } else if (stageName === 'timing') {
                            fileInput?.focus();
                        }

                        return;
                    }

                    if (stageName === 'security') {
                        manuallyOpenedSecurity = true;
                    }

                    if (stageName === 'file' || stageName === 'timing') {
                        openedStages[stageName] = true;
                    }

                    openStage(stageName, true);
                });
            });

            stageHeadings.forEach((heading) => {
                heading.addEventListener('click', (event) => {
                    if (event.target.closest('button, a, input, select, textarea, label')) {
                        return;
                    }

                    const stage = heading.closest('.send-accordion-stage');
                    const stageName = stage?.dataset.stage;

                    if (!stageName || !stage) {
                        return;
                    }

                    const isOpen = !stage.classList.contains('send-accordion-collapsed');
                    setStageExpanded(stageName, !isOpen);

                    if (stageName === 'security') {
                        manuallyOpenedSecurity = !isOpen;
                    }
                });
            });

            sendForm.addEventListener('submit', async (event) => {
                if (programmaticSubmit) {
                    if (fileInput) {
                        fileInput.disabled = true;
                    }
                    sendSubmit.disabled = true;
                    setChunkStatus(translations.uploadFinalize, 100);
                    return;
                }

                if (chunkUploading) {
                    event.preventDefault();
                    return;
                }

                if (uploadedFileTokenInput.value) {
                    if (fileInput) {
                        fileInput.disabled = true;
                    }
                    sendSubmit.disabled = true;
                    setChunkStatus(translations.uploadFinalize, 100);
                    return;
                }

                if (fileInput?.files?.[0]) {
                    event.preventDefault();
                    const uploadedToken = await uploadSelectedFile();

                    if (uploadedToken) {
                        programmaticSubmit = true;
                        if (fileInput) {
                            fileInput.disabled = true;
                        }
                        sendSubmit.disabled = true;
                        setChunkStatus(translations.uploadFinalize, 100);

                        if (typeof sendForm.requestSubmit === 'function') {
                            sendForm.requestSubmit(sendSubmit);
                        } else {
                            sendForm.submit();
                        }
                    }
                }
            });

            toggleCustomExpire();
            togglePublicLinkFields();
            toggleDestinationState();
            rebuildExpireOptions();
            renderReceiverCapabilityNote();
            syncWizardVisibility();

            if (!receiverLocked && receiverInput?.value.trim()) {
                lookupReceiver();
            }

            if (uploadedFileTokenInput.value) {
                fileDropzone.classList.add('has-file');
                setChunkStatus(translations.uploadReady, 100);
            } else {
                chunkUploadBox.style.display = 'none';
            }

            updateSummary();
        })();
    </script>
@endsection

@extends('layouts.app')

@section('page_title', __('ui.quick_send.title'))

@section('content')
    @php
        $hasOldState = $errors->any()
            || old('sender_name')
            || old('sender_contact')
            || old('receiver')
            || old('message');
        $result = session('quick_send_result');
    @endphp

    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.quick_send.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.quick_send.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button primary" href="#quick-send-form">{{ __('ui.quick_send.start_send') }}</a>
            <a class="button" href="{{ route('login') }}">{{ __('ui.quick_send.open_login') }}</a>
            <a class="button" href="{{ route('register') }}">{{ __('ui.quick_send.open_register') }}</a>
        </div>
    </section>

    @if(is_array($result))
        <section class="panel" style="margin-bottom: 18px;">
            <h2 style="margin-bottom: 8px;">{{ __('ui.quick_send.success_body', ['receiver' => $result['receiver'] ?? '-']) }}</h2>
            <div class="list">
                <div class="item">
                    <div>{{ __('ui.quick_send.success_file', ['file' => $result['file_name'] ?? '-']) }}</div>
                    <span class="badge">{{ __('ui.common.active') }}</span>
                </div>
                <div class="item">
                    <div>{{ __('ui.quick_send.success_expire', ['time' => $result['expires_at'] ?? '-']) }}</div>
                    <span class="badge">{{ __('ui.quick_send.expiry') }}</span>
                </div>
            </div>
        </section>
    @endif

    <section class="messenger-layout three-columns">
        <aside class="wizard-sidebar">
            <div class="panel">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 8px;">{{ __('ui.quick_send.summary_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.quick_send.summary_body').' '.__('ui.quick_send.login_hint')])
                </div>
                <div class="list" style="margin-top: 18px;">
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.quick_send.max_size') }}</strong>
                            <div class="muted">{{ __('ui.send.max_size_value', ['size' => $maxFileSizeMb]) }}</div>
                        </div>
                        <span class="badge">{{ __('ui.common.settings') }}</span>
                    </div>
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.quick_send.expiry') }}</strong>
                            <div class="muted">{{ __('ui.send.default_expire_with_hours', ['hours' => $defaultExpireHours]) }}</div>
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
                </div>
            </div>
        </aside>

        <section class="panel">
            <form id="quick-send-form" method="post" action="{{ route('quick-send.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="section-block">
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.send.upload_first_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.send.upload_first_body').' '.__('ui.send.options_reveal_hint')])
                        </div>
                    </div>

                    <div id="file-dropzone" class="file-dropzone">
                        <div class="dropzone-copy">
                            <strong>{{ __('ui.send.dropzone_hint') }}</strong>
                            <div class="muted">{{ __('ui.send.dropzone_or') }} {{ __('ui.send.dropzone_pick') }}</div>
                        </div>
                        <span class="dropzone-action">{{ __('ui.send.dropzone_pick') }}</span>
                        <input id="file" name="file" type="file" required>
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
                </div>

                <div class="section-block wizard-stage" @unless($hasOldState) hidden @endunless>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.quick_send.sender_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.quick_send.sender_body')])
                        </div>
                    </div>
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="sender_name">{{ __('ui.quick_send.sender_name') }}</label>
                            <input id="sender_name" name="sender_name" value="{{ old('sender_name') }}" required>
                        </div>
                        <div class="field">
                            <div class="label-with-help">
                                <label for="sender_contact">{{ __('ui.quick_send.sender_contact') }}</label>
                                @include('partials.inline-help', ['text' => __('ui.quick_send.sender_contact_hint')])
                            </div>
                            <input id="sender_contact" name="sender_contact" value="{{ old('sender_contact') }}">
                        </div>
                    </div>
                </div>

                <div class="section-block wizard-stage" @unless($hasOldState) hidden @endunless>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.quick_send.receiver_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.quick_send.receiver_body')])
                        </div>
                    </div>
                    <div class="field">
                        <label for="receiver">{{ __('ui.quick_send.receiver') }}</label>
                        <input id="receiver" name="receiver" value="{{ old('receiver', $prefilledReceiver) }}" placeholder="{{ __('ui.quick_send.receiver_placeholder') }}" required>
                    </div>
                </div>

                <div class="section-block wizard-stage" @unless($hasOldState) hidden @endunless>
                    <div class="section-heading">
                        <div class="title-with-help">
                            <h3>{{ __('ui.quick_send.message_title') }}</h3>
                            @include('partials.inline-help', ['text' => __('ui.quick_send.message_body')])
                        </div>
                    </div>
                    <div class="field">
                        <label for="message">{{ __('ui.quick_send.message') }}</label>
                        <textarea id="message" name="message" placeholder="{{ __('ui.quick_send.message_placeholder') }}">{{ old('message') }}</textarea>
                    </div>
                </div>

                <div class="action-bar wizard-stage" @unless($hasOldState) hidden @endunless>
                    <button class="button primary" type="submit">{{ __('ui.quick_send.send_file') }}</button>
                    <a class="button" href="{{ route('login') }}">{{ __('ui.quick_send.open_login') }}</a>
                </div>
            </form>
        </section>
    </section>

    <script>
        (() => {
            const fileInput = document.getElementById('file');
            const fileDropzone = document.getElementById('file-dropzone');
            const fileCard = document.getElementById('file-card');
            const fileName = document.getElementById('file-name');
            const fileMeta = document.getElementById('file-meta');
            const stages = Array.from(document.querySelectorAll('.wizard-stage'));

            const formatBytes = (bytes) => {
                const units = ['B', 'KB', 'MB', 'GB'];
                let size = Number(bytes) || 0;
                let unitIndex = 0;

                while (size >= 1024 && unitIndex < units.length - 1) {
                    size /= 1024;
                    unitIndex += 1;
                }

                return new Intl.NumberFormat(document.documentElement.lang || undefined, {
                    maximumFractionDigits: unitIndex === 0 ? 0 : 1,
                }).format(size) + ' ' + units[unitIndex];
            };

            const setExpanded = (expanded) => {
                stages.forEach((stage) => {
                    stage.hidden = !expanded;
                });
            };

            const updateFileCard = () => {
                const selectedFile = fileInput?.files?.[0];

                if (!selectedFile) {
                    fileCard.style.display = 'none';
                    fileDropzone.classList.remove('has-file');
                    setExpanded(false);
                    return;
                }

                fileName.textContent = selectedFile.name;
                fileMeta.textContent = formatBytes(selectedFile.size) + ' | ' + (selectedFile.type || @json(__('ui.send.unknown_type')));
                fileCard.style.display = 'block';
                fileDropzone.classList.add('has-file');
                setExpanded(true);
            };

            fileInput?.addEventListener('change', updateFileCard);

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
                if (!fileInput || !event.dataTransfer?.files?.length) {
                    return;
                }

                fileInput.files = event.dataTransfer.files;
                updateFileCard();
            });

            if (fileInput?.files?.length) {
                updateFileCard();
            }
        })();
    </script>
@endsection

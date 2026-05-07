@extends('layouts.app')

@section('page_title', ($conversationMode ?? 'user') === 'storage' ? (app()->getLocale() === 'fa' ? 'تبادل با فضای شخصی' : 'Personal storage exchange') : __('ui.exchange.title', ['user' => $otherUser->username]))

@section('content')
    @php
        $copyLabel = __('ui.actions.copy_public_link');
        $isStorageConversation = ($conversationMode ?? 'user') === 'storage';
        $storageLabel = app()->getLocale() === 'fa' ? 'فضای شخصی' : 'Personal storage';
        $storageSubtitle = app()->getLocale() === 'fa'
            ? 'فایل‌هایی که به فضای ذخیره‌سازی خودت فرستاده‌ای'
            : 'Files you have sent into your own storage';
        $workspaceOpenFilteredLabel = app()->getLocale() === 'fa' ? 'باز کردن فضای مرتبط' : 'Open related storage';
        $workspacePanelTitle = app()->getLocale() === 'fa' ? 'فضای شخصی در این تبادل' : 'Personal storage in this exchange';
        $workspacePanelBody = app()->getLocale() === 'fa'
            ? ':count فایل بدون انقضای workspace الان به این گفتگو متصل است.'
            : ':count no-expiry workspace files are currently connected to this conversation.';
        $workspaceEmptyTitle = app()->getLocale() === 'fa'
            ? 'هنوز فایل workspace برای این تبادل ثبت نشده است'
            : 'No workspace file is connected to this exchange yet';
        $workspaceEmptyBody = app()->getLocale() === 'fa'
            ? 'وقتی فایل‌های بدون انقضای این گفتگو در workspace ذخیره شوند، اینجا دیده می‌شوند.'
            : 'No-expiry files from this conversation will appear here once they are stored in the workspace.';
        $workspaceOpenLabel = app()->getLocale() === 'fa' ? 'باز کردن کل فضای شخصی' : 'Open full storage';
        $conversationTitle = $isStorageConversation
            ? (app()->getLocale() === 'fa' ? 'تبادل با فضای شخصی' : 'Personal storage exchange')
            : __('ui.exchange.title', ['user' => $otherUser->username]);
        $conversationName = $isStorageConversation ? $storageLabel : $otherUser->username;
        $conversationSubline = $isStorageConversation ? $storageSubtitle : ($otherUser->full_name ?: __('ui.send.without_full_name'));
        $searchAction = $isStorageConversation ? route('conversations.storage') : route('conversations.show', $otherUser);
        $primaryCtaHref = $isStorageConversation ? route('files.create', ['destination' => 'storage']) : route('files.create', ['receiver' => $otherUser->username]);
        $primaryCtaLabel = $isStorageConversation ? __('ui.send.save_to_storage') : __('ui.exchange.send_new_file');
        $secondaryCtaHref = $isStorageConversation ? route('storage.index') : route('history.index', ['user' => $otherUser->username]);
        $secondaryCtaLabel = $isStorageConversation ? (app()->getLocale() === 'fa' ? 'باز کردن فضای شخصی' : 'Open storage') : __('ui.exchange.user_history');
    @endphp

    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ $conversationTitle }}</h1>
        <div class="hero-actions">
            <a class="button primary" href="{{ $primaryCtaHref }}">{{ $primaryCtaLabel }}</a>
            <a class="button" href="{{ $secondaryCtaHref }}">{{ $secondaryCtaLabel }}</a>
            <a class="button" href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'fa' ? 'en' : 'fa']) }}">
                {{ __('ui.locales.switch') }}: {{ app()->getLocale() === 'fa' ? __('ui.locales.en') : __('ui.locales.fa') }}
            </a>
        </div>
    </section>

    <section class="messenger-layout three-columns">
        <aside class="panel thread-list-panel conversation-mobile-sidebar">
            <div class="thread-search">
                <div class="action-bar" style="margin-top:0; margin-bottom:12px;">
                    <h2 style="margin:0;">{{ __('ui.exchange.recent_users') }}</h2>
                    @if(($totalUnreadThreads ?? 0) > 0)
                        <span class="soft-badge"><span class="notification-dot"></span>{{ __('ui.inbox.unread_threads', ['count' => $totalUnreadThreads]) }}</span>
                    @elseif(($totalUnreadNotifications ?? 0) > 0)
                        <span class="soft-badge">{{ __('ui.inbox.new_notifications', ['count' => $totalUnreadNotifications]) }}</span>
                    @endif
                </div>
                <form method="get" action="{{ $searchAction }}">
                    <div class="field" style="margin-bottom: 0;">
                        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.exchange.search_placeholder') }}">
                    </div>
                </form>
            </div>

            <div class="thread-list">
                @forelse($threads as $thread)
                    @php
                        $isStorageThread = ($thread['type'] ?? 'user') === 'storage';
                        $name = $isStorageThread ? ($thread['label'] ?? $storageLabel) : ($thread['user']->full_name ?: $thread['user']->username);
                        $initial = $isStorageThread ? 'S' : mb_substr($name, 0, 1);
                        $threadHref = $isStorageThread
                            ? route('conversations.storage', $search ? ['q' => $search] : [])
                            : route('conversations.show', $thread['user'], $search ? ['q' => $search] : []);
                        $threadActive = ($isStorageConversation && $isStorageThread)
                            || (! $isStorageConversation && ! $isStorageThread && $thread['user']->id === $otherUser->id);
                    @endphp
                    <a class="thread-card {{ $thread['unread'] > 0 ? 'unread' : '' }} {{ $threadActive ? 'active' : '' }}" href="{{ $threadHref }}">
                        <div class="thread-main">
                            <span class="avatar">{{ $initial }}</span>
                            <div class="thread-meta">
                                <strong>{{ $isStorageThread ? $name : $thread['user']->username }}</strong>
                                @if(! $isStorageThread && $thread['user']->full_name)
                                    <div class="muted">{{ $thread['user']->full_name }}</div>
                                @elseif($isStorageThread)
                                    <div class="muted">{{ app()->getLocale() === 'fa' ? 'تبادل فایل‌های ارسالی به storage' : 'Exchange for files sent to storage' }}</div>
                                @endif
                                <div class="muted">{{ $thread['latest']?->file?->original_name ?: __('ui.inbox.without_file') }}</div>
                            </div>
                        </div>
                        <div class="actions">
                            @if($thread['unread'] > 0)
                                <span class="badge">{{ $thread['unread'] }}</span>
                                <span class="notification-dot" aria-hidden="true"></span>
                            @endif
                            <span class="muted">{{ $thread['latest']?->created_at?->diffForHumans() }}</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <span class="empty-icon">âŒ•</span>
                        <strong>{{ __('ui.exchange.empty_search_title') }}</strong>
                        <div class="muted">{{ __('ui.exchange.empty_search_body') }}</div>
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="panel">
            @php
                $headerInitial = $isStorageConversation ? 'S' : mb_substr($otherUser->full_name ?: $otherUser->username, 0, 1);
            @endphp
            <div class="conversation-header">
                <div class="thread-main">
                    <a class="button mobile-only" href="{{ route('inbox') }}">{{ __('ui.common.back') }}</a>
                    <span class="avatar large">{{ $headerInitial }}</span>
                    <div class="meta-stack">
                        <h1 style="margin:0;">{{ $conversationName }}</h1>
                        <p class="muted" style="margin:0;">{{ $conversationSubline }}</p>
                    </div>
                </div>
                <div class="actions">
                    <a class="button" href="{{ route('inbox') }}">{{ __('ui.exchange.back_to_inbox') }}</a>
                    <a class="button" href="{{ $secondaryCtaHref }}">{{ $secondaryCtaLabel }}</a>
                    <a class="button" href="{{ $isStorageConversation ? route('storage.index') : route('storage.index', ['contact' => $otherUser->username]) }}">{{ $workspaceOpenFilteredLabel }}</a>
                    <a class="button primary" href="{{ $primaryCtaHref }}">{{ $primaryCtaLabel }}</a>
                </div>
            </div>

            @if(session('public_link_url'))
                <div class="status" style="margin-top:14px;">
                    {{ __('ui.exchange.public_link_ready') }}
                    <button class="button" type="button" data-copy-link="{{ session('public_link_url') }}">{{ $copyLabel }}</button>
                </div>
            @endif

            @if(! $isStorageConversation)
                <div class="panel" style="margin-top: 18px; padding: 16px; background: rgba(15, 118, 110, 0.04);">
                    <div class="section-heading" style="margin-bottom: 14px;">
                        <div>
                            <h2 style="margin-bottom: 6px;">{{ $workspacePanelTitle }}</h2>
                            <p class="muted" style="margin: 0;">{{ str_replace(':count', number_format($workspaceFiles->count()), $workspacePanelBody) }}</p>
                        </div>
                        <a class="button" href="{{ route('storage.index', ['contact' => $otherUser->username]) }}">{{ $workspaceOpenLabel }}</a>
                    </div>

                    @if(($workspaceFiles ?? collect())->isNotEmpty())
                        <div class="list">
                            @foreach($workspaceFiles as $workspaceFile)
                                <div class="item" style="align-items: flex-start; gap: 14px;">
                                    <div style="flex: 1; min-width: 0;">
                                        <strong>{{ $workspaceFile->original_name }}</strong>
                                        <div class="muted">{{ $workspaceFile->readableSize() }} Â· {{ $workspaceFile->created_at?->diffForHumans() }}</div>
                                        @if($workspaceFile->getAttribute('workspace_folder_name'))
                                            <div class="muted" style="margin-top: 6px;">{{ __('ui.storage.in_folder', ['folder' => $workspaceFile->getAttribute('workspace_folder_name')]) }}</div>
                                        @endif
                                        @if($workspaceFile->getAttribute('note_excerpt'))
                                            <div class="muted" style="margin-top: 6px;">{{ $workspaceFile->getAttribute('note_excerpt') }}</div>
                                        @endif
                                        <div class="message-statuses" style="margin-top: 8px;">
                                            <span class="badge">{{ __('ui.storage.scope_badge_'.$workspaceFile->getAttribute('workspace_context')) }}</span>
                                            <span class="badge">{{ __('ui.storage.no_expiry') }}</span>
                                            @if($workspaceFile->getAttribute('preview_type') === 'note')
                                                <span class="badge">{{ __('ui.storage.note_badge') }}</span>
                                            @else
                                                <span class="badge">{{ __('ui.file_types.'.$workspaceFile->category()) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="actions" style="justify-content: flex-end;">
                                        @if($workspaceFile->getAttribute('can_inline_preview'))
                                            <a class="button" href="{{ route('storage.preview', $workspaceFile) }}">{{ __('ui.actions.open_preview') }}</a>
                                        @endif
                                        <a class="button" href="{{ route('storage.download', $workspaceFile) }}">{{ __('ui.actions.download') }}</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state" style="margin-top: 0;">
                            <span class="empty-icon">&bull;</span>
                            <strong>{{ $workspaceEmptyTitle }}</strong>
                            <div class="muted">{{ $workspaceEmptyBody }}</div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="message-stream">
                @forelse($messages as $message)
                    @php
                        $isMine = $message->sender_id === auth()->id();
                        $file = $message->file;
                        $isExpiredByTime = $file->expires_at && $file->expires_at->isPast();
                        $isDownloadable = $file->status === \App\Models\SharedFile::STATUS_ACTIVE && ! $isExpiredByTime;
                        $isProtected = $file->isPasswordProtected();
                        $previewType = \App\Support\FilePreviewPolicy::detectType($file, $previewPolicy ?? []);
                        $canPreviewByPolicy = \App\Support\FilePreviewPolicy::canPreview($file, $previewPolicy ?? []);
                        $isUnlocked = in_array($message->file_id, $unlockedFileIds ?? [], true);
                        $canPreviewNow = $canPreviewByPolicy && (! $isProtected || $isUnlocked);
                        $scanState = $file->isSecurityScanPending() ? __('ui.statuses.scan_pending') : ($file->isSecurityApproved() ? __('ui.statuses.scan_clean') : __('ui.statuses.scan_rejected'));
                        $infoPayload = [
                            'name' => $file->original_name,
                            'size' => $file->readableSize(),
                            'extension' => $file->extension ?: __('ui.common.unknown'),
                            'sent_at' => $message->created_at->format('Y-m-d H:i'),
                            'expires_at' => $file->expires_at?->format('Y-m-d H:i') ?: __('ui.common.not_available'),
                            'scan' => $scanState,
                            'download_state' => $message->downloaded_at ? __('ui.exchange.downloaded_yes') : __('ui.exchange.downloaded_no'),
                            'public_link_state' => $message->public_link_enabled ? __('ui.exchange.public_on') : __('ui.exchange.public_off'),
                        ];
                    @endphp
                    <div class="message-row {{ $isMine ? 'mine' : 'theirs' }}">
                        <article class="message {{ $isMine ? 'mine' : 'theirs' }}">
                            <div class="message-header">
                                <div>
                                    <div class="muted">{{ $isStorageConversation ? $storageLabel : ($isMine ? __('ui.exchange.me') : $message->sender->username) }} Â· {{ $message->created_at->format('Y-m-d H:i') }}</div>
                                    @if($message->message)
                                        <p style="margin:10px 0 0;">{{ $message->message }}</p>
                                    @endif
                                </div>
                                <div class="message-tools">
                                    <button
                                        class="icon-button"
                                        type="button"
                                        data-file-info='@json($infoPayload, JSON_UNESCAPED_UNICODE)'
                                        aria-label="{{ __('ui.common.info') }}"
                                    >i</button>
                                    <button
                                        class="icon-button"
                                        type="button"
                                        data-menu-toggle
                                        aria-label="{{ __('ui.common.more') }}"
                                    >â‹®</button>
                                    <div class="message-menu" data-menu>
                                        @if($isDownloadable)
                                            <a class="message-menu-item" href="{{ route('file-sends.download', $message) }}">
                                                <span>â†“</span>
                                                <span>{{ $isProtected ? __('ui.actions.unlock_download') : __('ui.actions.download') }}</span>
                                            </a>
                                        @endif
                                        @if($canPreviewNow)
                                            <a class="message-menu-item" href="{{ route('file-sends.preview', $message) }}" target="_blank" rel="noopener">
                                                <span>â–£</span>
                                                <span>{{ __('ui.actions.open_preview') }}</span>
                                            </a>
                                        @endif
                                        @if(($publicLinkFeatureEnabled ?? false) && $isMine)
                                            @if($message->public_link_enabled && $message->public_token)
                                                <button class="message-menu-item" type="button" data-copy-link="{{ route('public-files.download', $message->public_token) }}">
                                                    <span>âŒ</span>
                                                    <span>{{ __('ui.actions.copy_public_link') }}</span>
                                                </button>
                                                <form method="post" action="{{ route('file-sends.public-link.update', $message) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="disable">
                                                    <button type="submit">
                                                        <span>âŠ˜</span>
                                                        <span>{{ __('ui.actions.disable_public_link') }}</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="{{ route('file-sends.public-link.update', $message) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="regenerate">
                                                    <button type="submit">
                                                        <span>â†»</span>
                                                        <span>{{ __('ui.actions.regenerate_public_link') }}</span>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('file-sends.public-link.update', $message) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="enable">
                                                    <button type="submit">
                                                        <span>ï¼‹</span>
                                                        <span>{{ __('ui.actions.enable_public_link') }}</span>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="exchange-card">
                                @include('partials.file-preview-card', ['send' => $message, 'previewPolicy' => $previewPolicy, 'unlockedFileIds' => $unlockedFileIds])
                                <div class="exchange-card-main">
                                    <div class="muted">{{ __('ui.file_types.'.$file->category()) }} Â· {{ $file->readableSize() }}</div>
                                    @if($previewType === 'other')
                                        <div class="muted">{{ __('ui.exchange.preview_not_available') }}</div>
                                    @elseif(! $canPreviewByPolicy)
                                        <div class="muted">{{ __('ui.exchange.preview_policy_off') }}</div>
                                    @elseif($isProtected && ! $isUnlocked)
                                        <div class="muted">{{ __('ui.exchange.password_required_preview') }}</div>
                                    @endif
                                    <div class="message-statuses">
                                        @if($isProtected)
                                            <span class="badge">{{ __('ui.statuses.locked') }}</span>
                                        @endif
                                        @if($message->downloaded_at)
                                            <span class="badge">{{ __('ui.statuses.downloaded') }}</span>
                                        @endif
                                        @if($file->status === \App\Models\SharedFile::STATUS_EXPIRED || $isExpiredByTime)
                                            <span class="badge">{{ __('ui.statuses.expired') }}</span>
                                        @endif
                                        @if($file->status === \App\Models\SharedFile::STATUS_DELETED)
                                            <span class="badge">{{ __('ui.statuses.deleted') }}</span>
                                        @endif
                                        @if($file->isSecurityScanPending())
                                            <span class="badge">{{ __('ui.statuses.security_pending') }}</span>
                                        @elseif(! $file->isSecurityApproved())
                                            <span class="badge">{{ __('ui.statuses.security_rejected') }}</span>
                                        @endif
                                        @if($message->public_link_enabled)
                                            <span class="badge">{{ __('ui.statuses.public') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                @empty
                    <div class="empty-state">
                        <span class="empty-icon">â—Œ</span>
                        <strong>{{ $isStorageConversation ? (app()->getLocale() === 'fa' ? 'هنوز فایلی به فضای شخصی نفرستاده‌اید' : 'No file has been sent to your personal storage yet') : __('ui.exchange.exchange_empty_title') }}</strong>
                        <div class="muted">{{ $isStorageConversation ? (app()->getLocale() === 'fa' ? 'با ارسال به فضای ذخیره‌سازی، این تبادل مثل یک گفتگو پر می‌شود.' : 'Once you send to storage, this exchange will fill like a normal conversation.') : __('ui.exchange.exchange_empty_body') }}</div>
                        <a class="button primary" href="{{ $primaryCtaHref }}">{{ $primaryCtaLabel }}</a>
                    </div>
                @endforelse
            </div>
        </section>
    </section>

    <div id="file-detail-modal" class="detail-modal" aria-hidden="true">
        <div class="detail-modal-card">
            <div class="section-heading" style="margin-bottom: 0;">
                <div>
                    <h2 style="margin-bottom: 6px;">{{ __('ui.exchange.info_title') }}</h2>
                    <p id="file-detail-name" class="muted" style="margin: 0;">{{ __('ui.exchange.info_unavailable') }}</p>
                </div>
                <button id="file-detail-close" class="icon-button" type="button" aria-label="{{ __('ui.common.close') }}">Ã—</button>
            </div>
            <div class="detail-grid">
                <div><strong>{{ __('ui.exchange.size') }}</strong><span id="file-detail-size"></span></div>
                <div><strong>{{ __('ui.exchange.extension') }}</strong><span id="file-detail-extension"></span></div>
                <div><strong>{{ __('ui.exchange.sent_at') }}</strong><span id="file-detail-sent"></span></div>
                <div><strong>{{ __('ui.exchange.expires_at') }}</strong><span id="file-detail-expires"></span></div>
                <div><strong>{{ __('ui.exchange.scan') }}</strong><span id="file-detail-scan"></span></div>
                <div><strong>{{ __('ui.exchange.download_state') }}</strong><span id="file-detail-download"></span></div>
                <div><strong>{{ __('ui.exchange.public_link_state') }}</strong><span id="file-detail-public"></span></div>
            </div>
        </div>
    </div>

    <script>
        const copyButtons = document.querySelectorAll('[data-copy-link]');
        const copiedLabel = @json(__('ui.common.copied'));
        const copyFailedLabel = @json(__('ui.common.copy_failed'));
        const copyDefaultLabel = @json($copyLabel);
        const infoButtons = document.querySelectorAll('[data-file-info]');
        const menuButtons = document.querySelectorAll('[data-menu-toggle]');
        const menus = document.querySelectorAll('[data-menu]');
        const detailModal = document.getElementById('file-detail-modal');
        const detailClose = document.getElementById('file-detail-close');

        const setTemporaryLabel = (button, text) => {
            const original = button.textContent.trim() || copyDefaultLabel;
            button.textContent = text;

            window.setTimeout(() => {
                button.textContent = button.dataset.copyLabel || original || copyDefaultLabel;
            }, 1600);
        };

        copyButtons.forEach((button) => {
            if (!button.dataset.copyLabel) {
                button.dataset.copyLabel = button.textContent.trim() || copyDefaultLabel;
            }

            button.addEventListener('click', async () => {
                const link = button.getAttribute('data-copy-link');

                if (!link) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(link);
                    setTemporaryLabel(button, copiedLabel);
                } catch (error) {
                    setTemporaryLabel(button, copyFailedLabel);
                }
            });
        });

        const closeMenus = () => {
            menus.forEach((menu) => menu.classList.remove('open'));
        };

        menuButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const menu = button.parentElement.querySelector('[data-menu]');
                const isOpen = menu.classList.contains('open');
                closeMenus();

                if (!isOpen) {
                    menu.classList.add('open');
                }
            });
        });

        infoButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const payload = JSON.parse(button.getAttribute('data-file-info') || '{}');

                document.getElementById('file-detail-name').textContent = payload.name || @json(__('ui.exchange.info_unavailable'));
                document.getElementById('file-detail-size').textContent = payload.size || '-';
                document.getElementById('file-detail-extension').textContent = payload.extension || '-';
                document.getElementById('file-detail-sent').textContent = payload.sent_at || '-';
                document.getElementById('file-detail-expires').textContent = payload.expires_at || '-';
                document.getElementById('file-detail-scan').textContent = payload.scan || '-';
                document.getElementById('file-detail-download').textContent = payload.download_state || '-';
                document.getElementById('file-detail-public').textContent = payload.public_link_state || '-';
                detailModal.classList.add('open');
            });
        });

        detailClose?.addEventListener('click', () => detailModal.classList.remove('open'));
        detailModal?.addEventListener('click', (event) => {
            if (event.target === detailModal) {
                detailModal.classList.remove('open');
            }
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-menu]') && !event.target.closest('[data-menu-toggle]')) {
                closeMenus();
            }
        });
    </script>
@endsection

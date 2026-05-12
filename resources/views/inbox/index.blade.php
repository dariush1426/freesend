@extends('layouts.app')

@section('page_title', __('ui.inbox.title'))

@section('content')
    @php
        $openContactStorageLabel = app()->getLocale() === 'fa' ? 'باز کردن فایل‌های این مخاطب در فضای شخصی' : 'Open this contact in storage';
    @endphp
    <section class="page-hero" style="margin-bottom: 18px;">
        <div class="title-with-help">
            <h1>{{ __('ui.inbox.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.inbox.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button primary" href="{{ route('files.create') }}">{{ __('ui.inbox.start_send') }}</a>
            <a class="button" href="{{ route('history.index') }}">{{ __('ui.inbox.history') }}</a>
            <a class="button" href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'fa' ? 'en' : 'fa']) }}">
                {{ __('ui.locales.switch') }}: {{ app()->getLocale() === 'fa' ? __('ui.locales.en') : __('ui.locales.fa') }}
            </a>
        </div>
    </section>

    @if(session('public_links') && is_array(session('public_links')) && count(session('public_links')) > 0)
        <section class="panel" style="margin-bottom:16px;">
            <h2 style="margin-bottom:10px;">{{ __('ui.inbox.public_links_title') }}</h2>
            <div class="list">
                @foreach(session('public_links') as $link)
                    <div class="item">
                        <div>
                            <strong>{{ $link['receiver'] ?? __('ui.send.receiver') }}</strong>
                            <div class="muted">{{ $link['url'] ?? '' }}</div>
                        </div>
                        <button class="button" type="button" data-copy-link="{{ $link['url'] ?? '' }}">{{ __('ui.common.copy_link') }}</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="messenger-layout three-columns">
        <aside class="panel thread-list-panel">
            <div class="thread-search">
                <div class="action-bar" style="margin-top:0; margin-bottom:12px;">
                    <div class="title-with-help">
                        <h2 style="margin:0;">{{ __('ui.inbox.recent_users') }}</h2>
                        @include('partials.inline-help', ['text' => __('ui.inbox.recent_users_body')])
                    </div>
                    @if(($totalUnreadThreads ?? 0) > 0)
                        <span class="soft-badge"><span class="notification-dot"></span>{{ __('ui.inbox.unread_threads', ['count' => $totalUnreadThreads]) }}</span>
                    @elseif(($totalUnreadNotifications ?? 0) > 0)
                        <span class="soft-badge">{{ __('ui.inbox.new_notifications', ['count' => $totalUnreadNotifications]) }}</span>
                    @endif
                </div>
                <form method="get" action="{{ route('inbox') }}">
                    <div class="field" style="margin-bottom: 0;">
                        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.inbox.search_placeholder') }}">
                    </div>
                </form>
            </div>

            <div class="thread-list">
                @forelse($threads as $thread)
                    @php
                        $isStorageThread = ($thread['type'] ?? 'user') === 'storage';
                        $name = $isStorageThread
                            ? ($thread['label'] ?? (app()->getLocale() === 'fa' ? 'فضای شخصی' : 'Personal storage'))
                            : ($thread['user']->full_name ?: $thread['user']->username);
                        $initial = $isStorageThread ? 'S' : mb_substr($name, 0, 1);
                        $threadHref = $isStorageThread ? route('conversations.storage') : route('conversations.show', $thread['user']);
                        $isActiveThread = $activeThread
                            && (($isStorageThread && (($activeThread['type'] ?? 'user') === 'storage'))
                                || (! $isStorageThread && ($activeThread['type'] ?? 'user') !== 'storage' && $activeThread['user']->id === $thread['user']->id));
                    @endphp
                    <a class="thread-card {{ $thread['unread'] > 0 ? 'unread' : '' }} {{ $isActiveThread ? 'active' : '' }}" href="{{ $threadHref }}">
                        <div class="thread-main">
                            @if($isStorageThread)
                                <span class="avatar">{{ $initial }}</span>
                            @else
                                @include('partials.user-avatar', ['user' => $thread['user']])
                            @endif
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
                            @endif
                            @if($thread['unread'] > 0)
                                <span class="notification-dot" aria-hidden="true"></span>
                            @endif
                            <span class="muted">{{ $thread['latest']?->created_at?->diffForHumans() }}</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <span class="empty-icon">👥</span>
                        <strong>{{ __('ui.inbox.empty_title') }}</strong>
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="panel mobile-hidden">
            <div class="title-with-help">
                <h1>{{ __('ui.inbox.file_box') }}</h1>
                @include('partials.inline-help', ['text' => __('ui.inbox.start_send_body').' '.__('ui.inbox.history_body'), 'align' => 'end'])
            </div>

            <div class="grid cols-2" style="margin-top: 18px;">
                <div class="item">
                    <div>
                        <strong>{{ __('ui.inbox.start_send') }}</strong>
                    </div>
                    <a class="button primary" href="{{ route('files.create') }}">{{ __('ui.inbox.start_send') }}</a>
                </div>
                <div class="item">
                    <div>
                        <strong>{{ __('ui.history.title') }}</strong>
                    </div>
                    <span class="badge">{{ __('ui.inbox.ready') }}</span>
                </div>
            </div>

            @if($activeThread)
                @php
                    $activeIsStorageThread = ($activeThread['type'] ?? 'user') === 'storage';
                    $activeName = $activeIsStorageThread
                        ? ($activeThread['label'] ?? (app()->getLocale() === 'fa' ? 'فضای شخصی' : 'Personal storage'))
                        : ($activeThread['user']->full_name ?: $activeThread['user']->username);
                    $activeInitial = $activeIsStorageThread ? 'S' : mb_substr($activeName, 0, 1);
                @endphp
                <div class="panel conversation-card" style="margin-top: 18px;">
                    <div class="thread-main">
                        @if($activeIsStorageThread)
                            <span class="avatar large">{{ $activeInitial }}</span>
                        @else
                            @include('partials.user-avatar', ['user' => $activeThread['user'], 'class' => 'large'])
                        @endif
                        <div class="meta-stack">
                            <h2 style="margin:0;display:flex;align-items:center;gap:8px;"><span>{{ $activeIsStorageThread ? $activeName : $activeThread['user']->username }}</span></h2>
                            @if(! $activeIsStorageThread && $activeThread['user']->full_name)
                                <span class="muted">{{ $activeThread['user']->full_name }}</span>
                            @elseif($activeIsStorageThread)
                                <span class="muted">{{ app()->getLocale() === 'fa' ? 'فایل‌هایی که به فضای ذخیره‌سازی خودت فرستاده‌ای' : 'Files you have sent into your own storage' }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="action-bar">
                        <span class="muted">{{ __('ui.inbox.latest_interaction', ['time' => $activeThread['latest']?->created_at?->diffForHumans()]) }}</span>
                        <a class="button primary" href="{{ $activeIsStorageThread ? route('conversations.storage') : route('conversations.show', $activeThread['user']) }}">{{ __('ui.inbox.open_exchange') }}</a>
                        <a class="button" href="{{ $activeIsStorageThread ? route('files.create', ['destination' => 'storage']) : route('files.create', ['receiver' => $activeThread['user']->username]) }}">{{ $activeIsStorageThread ? __('ui.send.save_to_storage') : __('ui.inbox.send_to_this_user') }}</a>
                        <a class="button" href="{{ $activeIsStorageThread ? route('storage.index') : route('storage.index', ['contact' => $activeThread['user']->username]) }}">{{ $openContactStorageLabel }}</a>
                    </div>
                </div>
            @endif
        </section>
    </section>

    <script>
        const copyButtons = document.querySelectorAll('[data-copy-link]');
        const copyLabel = @json(__('ui.common.copy_link'));
        const copiedLabel = @json(__('ui.common.copied'));
        const copyFailedLabel = @json(__('ui.common.copy_failed'));

        copyButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                const link = button.getAttribute('data-copy-link');

                if (!link) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(link);
                    button.textContent = copiedLabel;
                } catch (error) {
                    button.textContent = copyFailedLabel;
                }

                setTimeout(() => {
                    button.textContent = copyLabel;
                }, 1500);
            });
        });
    </script>
@endsection

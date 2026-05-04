@extends('layouts.app')

@section('page_title', __('ui.inbox.title'))

@section('content')
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
                        $name = $thread['user']->full_name ?: $thread['user']->username;
                        $initial = mb_substr($name, 0, 1);
                    @endphp
                    <a class="thread-card {{ $thread['unread'] > 0 ? 'unread' : '' }} {{ $activeThread && $activeThread['user']->id === $thread['user']->id ? 'active' : '' }}" href="{{ route('conversations.show', $thread['user']) }}">
                        <div class="thread-main">
                            <span class="avatar">{{ $initial }}</span>
                            <div class="thread-meta">
                                <strong>{{ $thread['user']->username }}</strong>
                                @if($thread['user']->full_name)
                                    <div class="muted">{{ $thread['user']->full_name }}</div>
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
                    $activeName = $activeThread['user']->full_name ?: $activeThread['user']->username;
                    $activeInitial = mb_substr($activeName, 0, 1);
                @endphp
                <div class="panel conversation-card" style="margin-top: 18px;">
                    <div class="thread-main">
                        <span class="avatar large">{{ $activeInitial }}</span>
                        <div class="meta-stack">
                            <h2 style="margin:0;display:flex;align-items:center;gap:8px;"><span>{{ $activeThread['user']->username }}</span></h2>
                            @if($activeThread['user']->full_name)
                                <span class="muted">{{ $activeThread['user']->full_name }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="action-bar">
                        <span class="muted">{{ __('ui.inbox.latest_interaction', ['time' => $activeThread['latest']?->created_at?->diffForHumans()]) }}</span>
                        <a class="button primary" href="{{ route('conversations.show', $activeThread['user']) }}">{{ __('ui.inbox.open_exchange') }}</a>
                        <a class="button" href="{{ route('files.create', ['receiver' => $activeThread['user']->username]) }}">{{ __('ui.inbox.send_to_this_user') }}</a>
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

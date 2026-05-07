@extends('layouts.app')

@section('page_title', __('ui.notifications.page_title'))

@section('content')
    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.notifications.page_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.notifications.empty_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button" href="{{ route('dashboard') }}">{{ __('ui.common.back') }}</a>
            @if($notifications->count() > 0)
                <form method="post" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="button primary" type="submit">{{ __('ui.notifications.read_all') }}</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top: 18px;">
        <div class="list">
            @forelse($notifications as $notification)
                <a class="notification-link {{ $notification->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $notification) }}">
                    <strong>{{ $notification->title }}</strong>
                    <div class="muted">{{ $notification->body }}</div>
                    <div class="notification-meta">
                        <span>{{ $notification->created_at?->diffForHumans() }}</span>
                        @if(!$notification->read_at)
                            <span class="soft-badge">{{ __('ui.statuses.unread') }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <span class="empty-icon">&bull;</span>
                    <strong>{{ __('ui.notifications.empty_title') }}</strong>
                    <div class="muted">{{ __('ui.notifications.empty_body') }}</div>
                </div>
            @endforelse
        </div>

        @if(method_exists($notifications, 'links'))
            <div style="margin-top: 18px;">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
@endsection

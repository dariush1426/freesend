@extends('layouts.app')

@section('page_title', __('ui.dashboard.title'))

@section('content')
    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.dashboard.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.dashboard.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button primary" href="{{ route('files.create') }}">{{ __('ui.layout.send') }}</a>
            <a class="button" href="{{ route('inbox') }}">{{ __('ui.dashboard.open_inbox') }}</a>
            <a class="button" href="{{ route('history.index') }}">{{ __('ui.dashboard.browse_history') }}</a>
            <a class="button" href="{{ route('subscriptions.upgrade') }}">{{ __('ui.dashboard.upgrade_cta') }}</a>
            <a class="button" href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'fa' ? 'en' : 'fa']) }}">
                {{ __('ui.locales.switch') }}: {{ app()->getLocale() === 'fa' ? __('ui.locales.en') : __('ui.locales.fa') }}
            </a>
        </div>
    </section>

    <section class="stats-grid" style="margin-top: 18px;">
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.dashboard.received_kicker') }}</span>
            <div class="title-with-help">
                <h3>{{ __('ui.dashboard.received_title') }}</h3>
                @include('partials.inline-help', ['text' => __('ui.dashboard.received_hint')])
            </div>
            <strong class="value">{{ $receivedCount }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.dashboard.sent_kicker') }}</span>
            <div class="title-with-help">
                <h3>{{ __('ui.dashboard.sent_title') }}</h3>
                @include('partials.inline-help', ['text' => __('ui.dashboard.sent_hint')])
            </div>
            <strong class="value">{{ $sentCount }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.dashboard.unread_kicker') }}</span>
            <div class="title-with-help">
                <h3>{{ __('ui.dashboard.unread_title') }}</h3>
                @include('partials.inline-help', ['text' => __('ui.dashboard.unread_hint')])
            </div>
            <strong class="value">{{ $unreadCount }}</strong>
        </article>
    </section>

    <section class="grid cols-2" style="margin-top: 18px;">
        <div class="panel">
            <div class="section-heading">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 0;">{{ __('ui.dashboard.quick_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.dashboard.quick_body')])
                </div>
            </div>

            <div class="list">
                <div class="item">
                    <div>
                        <strong>{{ __('ui.dashboard.start_send') }}</strong>
                    </div>
                    <a class="button primary" href="{{ route('files.create') }}">{{ __('ui.dashboard.start') }}</a>
                </div>
                <div class="item">
                    <div>
                        <strong>{{ __('ui.dashboard.open_inbox') }}</strong>
                    </div>
                    <a class="button" href="{{ route('inbox') }}">{{ __('ui.dashboard.enter_inbox') }}</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="section-heading">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 0;">{{ __('ui.dashboard.plan_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.dashboard.plan_body')])
                </div>
            </div>

            <div class="list">
                <div class="item">
                    <div>
                        <strong>{{ $planProfile['plan']?->name ?? __('ui.subscriptions.no_active_plan') }}</strong>
                        <div class="muted">
                            {{ \App\Support\PlanPolicy::formatPlanPrice($planProfile['plan']) }}
                            &bull;
                            {{ \App\Support\PlanPolicy::formatPlanDuration($planProfile['plan']) }}
                        </div>
                    </div>
                    <a class="button primary" href="{{ route('subscriptions.upgrade') }}">{{ __('ui.dashboard.upgrade_cta') }}</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="section-heading">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 0;">{{ __('ui.dashboard.storage_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.dashboard.storage_body')])
                </div>
            </div>

            <div class="list">
                <div class="item">
                    <div>
                        <strong>{{ __('ui.dashboard.storage_files', ['count' => number_format($storageProfile['files_count'])]) }}</strong>
                        <div class="muted">
                            @if($storageProfile['has_unlimited_quota'])
                                {{ __('ui.dashboard.storage_unlimited') }}
                            @else
                                {{ __('ui.dashboard.storage_usage', [
                                    'used' => number_format((int) round($storageProfile['used_bytes'] / 1024 / 1024, 1)),
                                    'total' => number_format((int) ($storageProfile['quota_mb'] ?? 0)),
                                ]) }}
                            @endif
                        </div>
                        @if($storageProfile['is_full'] ?? false)
                            <div class="muted" style="margin-top: 6px; color: #b42318;">{{ __('ui.dashboard.storage_full') }}</div>
                        @elseif($storageProfile['is_near_capacity'] ?? false)
                            <div class="muted" style="margin-top: 6px; color: #b42318;">{{ __('ui.dashboard.storage_near_capacity') }}</div>
                        @endif
                    </div>
                    <a class="button primary" href="{{ route('storage.index') }}">{{ __('ui.dashboard.open_storage') }}</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="section-heading">
                <div class="title-with-help">
                    <h2 style="margin-bottom: 0;">{{ __('ui.dashboard.notifications_title') }}</h2>
                    @include('partials.inline-help', ['text' => __('ui.dashboard.notifications_body')])
                </div>
            </div>

            <div class="list">
                @forelse($notifications as $notification)
                    <div class="item">
                        <div>
                            <strong>{{ $notification->title }}</strong>
                            <div class="muted">{{ $notification->body }}</div>
                        </div>
                        @if(!$notification->read_at)
                            <span class="badge">{{ __('ui.common.new') }}</span>
                        @else
                            <span class="badge" style="background: rgba(17, 33, 28, 0.06); color: var(--muted); border-color: rgba(17, 33, 28, 0.08);">{{ __('ui.dashboard.read') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">
                        <span class="empty-icon">&bull;</span>
                        <strong>{{ __('ui.dashboard.empty_notifications_title') }}</strong>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection

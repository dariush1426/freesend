@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.plans.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.plans.title') }}</h1>
        <p class="muted">{{ __('admin.plans.body') }}</p>
        <div class="hero-actions">
            <a class="button primary" href="{{ route('admin.plans.create') }}">{{ __('admin.buttons.create_plan') }}</a>
            <a class="button" href="{{ route('admin.subscribers.index') }}">{{ __('admin.nav.subscribers') }}</a>
            <a class="button" href="{{ route('admin.orders.index') }}">{{ __('admin.nav.orders') }}</a>
            <a class="button" href="{{ route('subscriptions.upgrade') }}">{{ __('admin.buttons.browse_upgrade_page') }}</a>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.overview_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.overview_body') }}</p>

        <section class="stats-grid" style="margin-top: 18px;">
            @forelse($plans as $plan)
                <article class="stat-card">
                    <span class="card-kicker">{{ $plan->name }}</span>
                    <strong class="value" style="font-size: 1.2rem;">{{ \App\Support\PlanPolicy::formatPlanPrice($plan) }}</strong>
                    <div class="muted">{{ \App\Support\PlanPolicy::formatPlanDuration($plan) }}</div>
                    <div class="muted">{{ __('admin.plans.users_with_plan') }}: {{ number_format($plan->subscriptions_count) }}</div>
                    <div style="margin-top: 12px;">
                        <a class="button" href="{{ route('admin.plans.edit', $plan) }}">{{ __('admin.buttons.edit_plan') }}</a>
                    </div>
                </article>
            @empty
                <article class="panel">
                    <p class="muted">{{ __('admin.plans.empty') }}</p>
                </article>
            @endforelse
        </section>
    </section>

    <section class="panel">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.existing_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.existing_body') }}</p>

        <div class="list">
            @forelse($plans as $plan)
                <div class="item" style="display:block;">
                    <div class="thread-main" style="justify-content: space-between; gap: 16px; align-items:flex-start;">
                        <div class="meta-stack">
                            <strong>{{ $plan->name }}</strong>
                            <span class="muted">{{ $plan->slug }}</span>
                            @if($plan->description)
                                <span class="muted">{{ $plan->description }}</span>
                            @endif
                            <span class="muted">
                                {{ __('admin.plans.price') }}: {{ \App\Support\PlanPolicy::formatPlanPrice($plan) }}
                                &bull;
                                {{ __('admin.plans.duration') }}: {{ \App\Support\PlanPolicy::formatPlanDuration($plan) }}
                            </span>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            @if($plan->is_default)
                                <span class="badge">{{ __('admin.plans.default_badge') }}</span>
                            @endif
                            @unless($plan->is_active)
                                <span class="badge">{{ __('admin.plans.inactive_badge') }}</span>
                            @endunless
                            <a class="button" href="{{ route('admin.plans.edit', $plan) }}">{{ __('admin.buttons.edit_plan') }}</a>
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('admin.plans.empty') }}</p>
            @endforelse
        </div>
    </section>
@endsection

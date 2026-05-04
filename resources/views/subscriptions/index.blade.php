@extends('layouts.app')

@section('page_title', __('ui.subscriptions.title'))

@section('content')
    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.subscriptions.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.subscriptions.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            <a class="button" href="{{ route('dashboard') }}">{{ __('ui.common.back') }}</a>
        </div>
    </section>

    <section class="panel" style="margin-top: 18px; margin-bottom: 18px;">
        @if($gatewayReady)
            <div class="status">{{ __('ui.subscriptions.gateway_ready') }}</div>
        @else
            <div class="status" style="background: rgba(180, 83, 9, 0.08); color: #9a3412; border-color: rgba(180, 83, 9, 0.16);">
                {{ __('ui.subscriptions.gateway_not_ready') }}
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top: 18px; margin-bottom: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('ui.subscriptions.current_plan') }}</h2>
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
                @if($planProfile['plan'])
                    <span class="badge">{{ __('ui.subscriptions.plan_active') }}</span>
                @endif
            </div>
        </div>
    </section>

    <section class="grid cols-2">
        <div class="title-with-help" style="grid-column: 1 / -1;">
            <h2>{{ __('ui.subscriptions.available_plans') }}</h2>
            @include('partials.inline-help', ['text' => __('ui.subscriptions.hero_body'), 'align' => 'end'])
        </div>

        @foreach($plans as $plan)
            <article class="panel">
                <div class="thread-main" style="justify-content: space-between; align-items:flex-start; gap:12px;">
                    <div class="meta-stack">
                        <strong>{{ $plan->name }}</strong>
                        @if($plan->description)
                            <span class="muted">{{ $plan->description }}</span>
                        @endif
                    </div>
                    @if(($planProfile['plan']?->id ?? null) === $plan->id)
                        <span class="badge">{{ __('ui.subscriptions.plan_active') }}</span>
                    @endif
                </div>

                <div class="stats-grid" style="margin-top: 14px; margin-bottom: 14px;">
                    <article class="stat-card">
                        <span class="card-kicker">{{ __('admin.plans.price') }}</span>
                        <strong class="value" style="font-size:1.2rem;">{{ \App\Support\PlanPolicy::formatPlanPrice($plan) }}</strong>
                    </article>
                    <article class="stat-card">
                        <span class="card-kicker">{{ __('admin.plans.duration') }}</span>
                        <strong class="value" style="font-size:1.2rem;">{{ \App\Support\PlanPolicy::formatPlanDuration($plan) }}</strong>
                    </article>
                </div>

                <div class="field" style="margin-bottom: 12px;">
                    <label>{{ __('ui.subscriptions.includes') }}</label>
                    <div class="list">
                        @foreach([
                            'allow_public_links' => 'feature_public_links',
                            'allow_password_protection' => 'feature_passwords',
                            'allow_custom_expiry' => 'feature_custom_expiry',
                            'allow_never_expire' => 'feature_never_expire',
                            'allow_personal_storage' => 'feature_personal_storage',
                            'allow_team_features' => 'feature_team',
                            'allow_signature_workflow' => 'feature_signature',
                            'allow_folders' => 'feature_folders',
                            'allow_ai_features' => 'feature_ai',
                        ] as $field => $translationKey)
                            @if($plan->{$field})
                                <div class="item">
                                    <strong>{{ __('ui.subscriptions.'.$translationKey) }}</strong>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('ui.subscriptions.limits') }}</label>
                    <div class="list">
                        @if($plan->max_upload_size_mb)
                            <div class="item"><span>{{ __('ui.subscriptions.max_upload', ['size' => $plan->max_upload_size_mb]) }}</span></div>
                        @endif
                        @if($plan->max_storage_mb)
                            <div class="item"><span>{{ __('ui.subscriptions.max_storage', ['size' => $plan->max_storage_mb]) }}</span></div>
                        @endif
                        @if($plan->max_team_members)
                            <div class="item"><span>{{ __('ui.subscriptions.team_members', ['count' => $plan->max_team_members]) }}</span></div>
                        @endif
                    </div>
                </div>

                <form method="post" action="{{ route('subscriptions.purchase', $plan) }}" style="margin-top: 16px;">
                    @csrf
                    <button class="button primary" type="submit" @disabled(($planProfile['plan']?->id ?? null) === $plan->id)>{{ __('ui.subscriptions.choose_plan') }}</button>
                    <div class="muted" style="margin-top: 8px;">
                        {{ $gatewayReady || ! $plan->isPaid() ? __('ui.subscriptions.purchase_live') : __('ui.subscriptions.purchase_pending') }}
                    </div>
                </form>
            </article>
        @endforeach
    </section>

    <section class="panel" style="margin-top: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('ui.subscriptions.recent_orders') }}</h2>
        <div class="list">
            @forelse($recentOrders as $order)
                <div class="item">
                    <div>
                        <strong>{{ $order->order_number }}</strong>
                        <div class="muted">{{ $order->plan?->name }} &bull; {{ number_format($order->amount) }} {{ $order->currency }}</div>
                    </div>
                    <span class="badge">{{ __('ui.subscriptions.order_status_'.$order->status) }}</span>
                </div>
            @empty
                <div class="item">
                    <div class="muted">{{ __('ui.subscriptions.no_orders') }}</div>
                </div>
            @endforelse
        </div>
    </section>
@endsection

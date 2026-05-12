@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.subscribers.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.subscribers.title') }}</h1>
        <p class="muted">{{ __('admin.subscribers.body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.plans.index') }}">{{ __('admin.nav.plans') }}</a>
            <a class="button" href="{{ route('admin.plans.create') }}">{{ __('admin.buttons.create_plan') }}</a>
            <a class="button" href="{{ route('admin.orders.index') }}">{{ __('admin.nav.orders') }}</a>
        </div>
    </section>

    <section class="panel">
        <form method="get" action="{{ route('admin.subscribers.index') }}" style="margin-bottom: 16px;">
            <div class="grid cols-3">
                <div class="field" style="margin-bottom: 0;">
                    <label for="search">{{ __('admin.buttons.search') }}</label>
                    <input id="search" name="search" value="{{ $search }}" placeholder="{{ __('admin.plans.search_placeholder') }}">
                </div>
                <div class="field" style="align-self: end; margin-bottom: 0;">
                    <button class="button" type="submit">{{ __('admin.buttons.search') }}</button>
                </div>
            </div>
        </form>

        <div class="list">
            @foreach($users as $user)
                @php($activeSubscription = \App\Support\PlanPolicy::activeSubscriptionForUser($user))
                <div class="item" style="display:block;">
                    <div class="thread-main" style="justify-content: space-between; gap: 16px; align-items:flex-start;">
                        <div class="meta-stack">
                            <strong>{{ $user->username }}</strong>
                            <span class="muted">{{ $user->full_name ?: $user->email ?: $user->mobile }}</span>
                            <span class="muted">{{ __('admin.subscribers.orders_count') }}: {{ number_format($user->subscription_orders_count) }}</span>
                            @if($activeSubscription)
                                <span class="muted">{{ __('admin.plans.current_plan') }}: {{ $activeSubscription->plan?->name ?? '-' }}</span>
                                <span class="muted">
                                    {{ __('admin.plans.subscription_until') }}:
                                    {{ $activeSubscription->ends_at ? \App\Support\LocalizedDate::dateTime($activeSubscription->ends_at) : __('admin.plans.subscription_no_limit') }}
                                </span>
                            @else
                                <span class="muted">{{ __('admin.plans.no_subscription') }}</span>
                            @endif
                        </div>

                        <form method="post" action="{{ route('admin.subscribers.assign') }}" style="min-width: min(100%, 420px);">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">

                            <div class="grid cols-2">
                                <div class="field">
                                    <label>{{ __('admin.plans.current_plan') }}</label>
                                    <select name="plan_id" required>
                                        @foreach($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected($activeSubscription?->plan_id === $plan->id || (!$activeSubscription && $plan->is_default))>{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('admin.plan_fields.expires_at') }}</label>
                                    <input name="ends_at" type="datetime-local" value="{{ $activeSubscription?->ends_at?->format('Y-m-d\\TH:i') }}">
                                </div>
                            </div>

                            <div class="field">
                                <label>{{ __('admin.plan_fields.notes') }}</label>
                                <textarea name="notes"></textarea>
                            </div>

                            <button class="button primary" type="submit">{{ __('admin.buttons.assign_plan') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 16px;">
            {{ $users->links() }}
        </div>
    </section>
@endsection

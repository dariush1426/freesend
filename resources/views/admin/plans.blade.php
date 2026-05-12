@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.plans.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.plans.title') }}</h1>
        <p class="muted">{{ __('admin.plans.body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.dashboard') }}">{{ __('admin.nav.dashboard') }}</a>
            <a class="button" href="{{ route('admin.settings.edit') }}">{{ __('admin.nav.settings') }}</a>
            <a class="button" href="{{ route('subscriptions.upgrade') }}">{{ __('admin.buttons.browse_upgrade_page') }}</a>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.overview_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.overview_body') }}</p>

        <section class="stats-grid" style="margin-top: 18px;">
            @foreach($plans as $plan)
                <article class="stat-card">
                    <span class="card-kicker">{{ $plan->name }}</span>
                    <strong class="value" style="font-size: 1.2rem;">{{ \App\Support\PlanPolicy::formatPlanPrice($plan, 'ui.subscriptions') }}</strong>
                    <div class="muted">{{ \App\Support\PlanPolicy::formatPlanDuration($plan, 'ui.subscriptions') }}</div>
                    <div class="muted">{{ __('admin.plans.users_with_plan') }}: {{ number_format($plan->subscriptions_count) }}</div>
                </article>
            @endforeach
        </section>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.create_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.create_body') }}</p>

        <form method="post" action="{{ route('admin.plans.store') }}">
            @csrf

            <div class="grid cols-3">
                <div class="field">
                    <label for="plan-name">{{ __('admin.plan_fields.name') }}</label>
                    <input id="plan-name" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label for="plan-slug">{{ __('admin.plan_fields.slug') }}</label>
                    <input id="plan-slug" name="slug" value="{{ old('slug') }}" placeholder="premium-plus">
                </div>
                <div class="field">
                    <label for="plan-sort-order">{{ __('admin.plan_fields.sort_order') }}</label>
                    <input id="plan-sort-order" name="sort_order" type="number" min="0" value="{{ old('sort_order', '0') }}">
                </div>
            </div>

            <div class="field">
                <label for="plan-description">{{ __('admin.plan_fields.description') }}</label>
                <textarea id="plan-description" name="description">{{ old('description') }}</textarea>
            </div>

            <div class="grid cols-3">
                <div class="field">
                    <label for="plan-max-upload">{{ __('admin.plan_fields.max_upload_size_mb') }}</label>
                    <input id="plan-max-upload" name="max_upload_size_mb" type="number" min="1" value="{{ old('max_upload_size_mb') }}">
                </div>
                <div class="field">
                    <label for="plan-max-storage">{{ __('admin.plan_fields.max_storage_mb') }}</label>
                    <input id="plan-max-storage" name="max_storage_mb" type="number" min="1" value="{{ old('max_storage_mb') }}">
                </div>
                <div class="field">
                    <label for="plan-max-team">{{ __('admin.plan_fields.max_team_members') }}</label>
                    <input id="plan-max-team" name="max_team_members" type="number" min="1" value="{{ old('max_team_members') }}">
                </div>
            </div>

            <div class="grid cols-3">
                <div class="field">
                    <label for="plan-price">{{ __('admin.plan_fields.price_amount') }}</label>
                    <input id="plan-price" name="price_amount" type="number" min="0" value="{{ old('price_amount', '0') }}">
                </div>
                <div class="field">
                    <label for="plan-duration-value">{{ __('admin.plan_fields.duration_value') }}</label>
                    <input id="plan-duration-value" name="duration_value" type="number" min="1" value="{{ old('duration_value') }}">
                </div>
                <div class="field">
                    <label for="plan-duration-unit">{{ __('admin.plan_fields.duration_unit') }}</label>
                    <select id="plan-duration-unit" name="duration_unit">
                        <option value="">{{ __('ui.common.not_available') }}</option>
                        <option value="day" @selected(old('duration_unit') === 'day')>{{ __('admin.options.duration_unit.day') }}</option>
                        <option value="month" @selected(old('duration_unit') === 'month')>{{ __('admin.options.duration_unit.month') }}</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>{{ __('admin.plans.expire_options') }}</label>
                <div class="grid cols-4">
                    @foreach($expireOptionValues as $expireOptionValue)
                        <label class="checkbox-card">
                            <input type="checkbox" name="expire_options[]" value="{{ $expireOptionValue }}" @checked(in_array($expireOptionValue, old('expire_options', ['default', '1', '2', '5', '12', '24', 'custom']), true))>
                            <span>{{ $expireOptionValue }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label>{{ __('admin.plans.features') }}</label>
                <div class="grid cols-3">
                    @foreach([
                        'is_active',
                        'is_default',
                        'allow_public_links',
                        'allow_password_protection',
                        'allow_custom_expiry',
                        'allow_never_expire',
                        'allow_personal_storage',
                        'allow_team_features',
                        'allow_signature_workflow',
                        'allow_folders',
                        'allow_ai_features',
                    ] as $toggle)
                        <label class="checkbox-card">
                            <input type="checkbox" name="{{ $toggle }}" value="1" @checked(in_array($toggle, ['is_active', 'allow_public_links', 'allow_password_protection', 'allow_custom_expiry'], true) ? old($toggle, '1') : old($toggle))>
                            <span>{{ __('admin.plan_toggles.'.$toggle) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button class="button primary" type="submit">{{ __('admin.buttons.create_plan') }}</button>
        </form>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.existing_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.existing_body') }}</p>

        @forelse($plans as $plan)
            <article class="panel" style="margin-bottom: 14px; background: rgba(246, 248, 252, 0.6);">
                <div class="thread-main" style="justify-content: space-between; gap: 12px; margin-bottom: 14px;">
                    <div class="meta-stack">
                        <strong>{{ $plan->name }}</strong>
                        <span class="muted">{{ $plan->slug }}</span>
                        @if($plan->description)
                            <span class="muted">{{ $plan->description }}</span>
                        @endif
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @if($plan->is_default)
                            <span class="badge">{{ __('admin.plans.default_badge') }}</span>
                        @endif
                        @unless($plan->is_active)
                            <span class="badge">{{ __('admin.plans.inactive_badge') }}</span>
                        @endunless
                        <span class="badge">{{ \App\Support\PlanPolicy::formatPlanPrice($plan, 'ui.subscriptions') }}</span>
                        <span class="badge">{{ \App\Support\PlanPolicy::formatPlanDuration($plan, 'ui.subscriptions') }}</span>
                    </div>
                </div>

                <form method="post" action="{{ route('admin.plans.update', $plan) }}">
                    @csrf
                    @method('patch')

                    <div class="grid cols-3">
                        <div class="field">
                            <label>{{ __('admin.plan_fields.name') }}</label>
                            <input name="name" value="{{ $plan->name }}" required>
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.slug') }}</label>
                            <input name="slug" value="{{ $plan->slug }}">
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.sort_order') }}</label>
                            <input name="sort_order" type="number" min="0" value="{{ $plan->sort_order }}">
                        </div>
                    </div>

                    <div class="field">
                        <label>{{ __('admin.plan_fields.description') }}</label>
                        <textarea name="description">{{ $plan->description }}</textarea>
                    </div>

                    <div class="grid cols-3">
                        <div class="field">
                            <label>{{ __('admin.plan_fields.max_upload_size_mb') }}</label>
                            <input name="max_upload_size_mb" type="number" min="1" value="{{ $plan->max_upload_size_mb }}">
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.max_storage_mb') }}</label>
                            <input name="max_storage_mb" type="number" min="1" value="{{ $plan->max_storage_mb }}">
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.max_team_members') }}</label>
                            <input name="max_team_members" type="number" min="1" value="{{ $plan->max_team_members }}">
                        </div>
                    </div>

                    <div class="grid cols-3">
                        <div class="field">
                            <label>{{ __('admin.plan_fields.price_amount') }}</label>
                            <input name="price_amount" type="number" min="0" value="{{ $plan->price_amount ?? 0 }}">
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.duration_value') }}</label>
                            <input name="duration_value" type="number" min="1" value="{{ $plan->duration_value }}">
                        </div>
                        <div class="field">
                            <label>{{ __('admin.plan_fields.duration_unit') }}</label>
                            <select name="duration_unit">
                                <option value="">{{ __('ui.common.not_available') }}</option>
                                <option value="day" @selected($plan->duration_unit === 'day')>{{ __('admin.options.duration_unit.day') }}</option>
                                <option value="month" @selected($plan->duration_unit === 'month')>{{ __('admin.options.duration_unit.month') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label>{{ __('admin.plans.expire_options') }}</label>
                        <div class="grid cols-4">
                            @foreach($expireOptionValues as $expireOptionValue)
                                <label class="checkbox-card">
                                    <input type="checkbox" name="expire_options[]" value="{{ $expireOptionValue }}" @checked(in_array($expireOptionValue, $plan->expire_options ?? [], true))>
                                    <span>{{ $expireOptionValue }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="field">
                        <label>{{ __('admin.plans.features') }}</label>
                        <div class="grid cols-3">
                            @foreach([
                                'is_active',
                                'is_default',
                                'allow_public_links',
                                'allow_password_protection',
                                'allow_custom_expiry',
                                'allow_never_expire',
                                'allow_personal_storage',
                                'allow_team_features',
                                'allow_signature_workflow',
                                'allow_folders',
                                'allow_ai_features',
                            ] as $toggle)
                                <label class="checkbox-card">
                                    <input type="checkbox" name="{{ $toggle }}" value="1" @checked((bool) $plan->{$toggle})>
                                    <span>{{ __('admin.plan_toggles.'.$toggle) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button class="button primary" type="submit">{{ __('admin.buttons.save_plan') }}</button>
                </form>
            </article>
        @empty
            <p class="muted">{{ __('admin.plans.empty') }}</p>
        @endforelse
    </section>

    <section class="panel">
        <h2 style="margin-bottom: 8px;">{{ __('admin.plans.assignments_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.plans.assignments_body') }}</p>

        <form method="get" action="{{ route('admin.plans.index') }}" style="margin-bottom: 16px;">
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

                        <form method="post" action="{{ route('admin.plans.assign') }}" style="min-width: min(100%, 420px);">
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

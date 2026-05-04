@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.plans.edit_title', ['plan' => $plan->name]))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.plans.edit_title', ['plan' => $plan->name]) }}</h1>
        <p class="muted">{{ __('admin.plans.edit_body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.plans.index') }}">{{ __('admin.nav.plans') }}</a>
            <a class="button" href="{{ route('admin.plans.create') }}">{{ __('admin.buttons.create_plan') }}</a>
            <a class="button" href="{{ route('admin.subscribers.index') }}">{{ __('admin.nav.subscribers') }}</a>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <div class="list">
            <div class="item">
                <div>
                    <strong>{{ $plan->name }}</strong>
                    <div class="muted">{{ \App\Support\PlanPolicy::formatPlanPrice($plan) }} &bull; {{ \App\Support\PlanPolicy::formatPlanDuration($plan) }}</div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    @if($plan->is_default)
                        <span class="badge">{{ __('admin.plans.default_badge') }}</span>
                    @endif
                    @unless($plan->is_active)
                        <span class="badge">{{ __('admin.plans.inactive_badge') }}</span>
                    @endunless
                </div>
            </div>
        </div>
    </section>

    <section class="panel" style="max-width: 980px;">
        @include('admin.plans._form', [
            'plan' => $plan,
            'action' => route('admin.plans.update', $plan),
            'method' => 'patch',
            'submitLabel' => __('admin.buttons.save_plan'),
        ])
    </section>
@endsection

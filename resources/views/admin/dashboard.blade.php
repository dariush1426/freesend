@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.dashboard.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.dashboard.title') }}</h1>
        <p class="muted">{{ __('admin.dashboard.body') }}</p>
        <div class="hero-actions">
            <a class="button primary" href="{{ route('admin.settings.edit') }}">{{ __('admin.dashboard.open_settings') }}</a>
            <a class="button" href="{{ route('admin.plans.index') }}">{{ __('admin.dashboard.open_plans') }}</a>
            <a class="button" href="{{ route('admin.subscribers.index') }}">{{ __('admin.nav.subscribers') }}</a>
            <a class="button" href="{{ route('admin.orders.index') }}">{{ __('admin.nav.orders') }}</a>
            <a class="button" href="{{ route('dashboard') }}">{{ __('admin.dashboard.return_to_app') }}</a>
        </div>
    </section>

    <section class="stats-grid" style="margin-bottom: 18px;">
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.users') }}</span>
            <strong class="value">{{ number_format($totalUsers) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.admins') }}</span>
            <strong class="value">{{ number_format($adminUsers) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.transfers') }}</span>
            <strong class="value">{{ number_format($totalTransfers) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.files') }}</span>
            <strong class="value">{{ number_format($storedFiles) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.plans') }}</span>
            <strong class="value">{{ number_format($totalPlans) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.subscriptions') }}</span>
            <strong class="value">{{ number_format($activeSubscriptions) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.verified_emails') }}</span>
            <strong class="value">{{ number_format($verifiedEmails) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('admin.dashboard.verified_mobiles') }}</span>
            <strong class="value">{{ number_format($verifiedMobiles) }}</strong>
        </article>
    </section>

    <section class="panel">
        <h2>{{ __('admin.dashboard.readiness_title') }}</h2>
        <p class="muted" style="margin-top: 0;">{{ __('admin.dashboard.readiness_body') }}</p>
    </section>
@endsection

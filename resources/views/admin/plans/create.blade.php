@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.plans.create_title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.plans.create_title') }}</h1>
        <p class="muted">{{ __('admin.plans.create_body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.plans.index') }}">{{ __('admin.nav.plans') }}</a>
            <a class="button" href="{{ route('admin.subscribers.index') }}">{{ __('admin.nav.subscribers') }}</a>
        </div>
    </section>

    <section class="panel" style="max-width: 980px;">
        @include('admin.plans._form', [
            'action' => route('admin.plans.store'),
            'submitLabel' => __('admin.buttons.create_plan'),
        ])
    </section>
@endsection

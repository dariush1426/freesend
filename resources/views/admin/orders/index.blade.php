@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.orders.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.orders.title') }}</h1>
        <p class="muted">{{ __('admin.orders.body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.plans.index') }}">{{ __('admin.nav.plans') }}</a>
            <a class="button" href="{{ route('admin.subscribers.index') }}">{{ __('admin.nav.subscribers') }}</a>
            <a class="button" href="{{ route('admin.settings.edit') }}">{{ __('admin.nav.settings') }}</a>
        </div>
    </section>

    <section class="panel">
        <form method="get" action="{{ route('admin.orders.index') }}" style="margin-bottom: 16px;">
            <div class="grid cols-3">
                <div class="field" style="margin-bottom: 0;">
                    <label for="search">{{ __('admin.buttons.search') }}</label>
                    <input id="search" name="search" value="{{ $search }}" placeholder="{{ __('admin.orders.search_placeholder') }}">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label for="status">{{ __('ui.common.status') }}</label>
                    <select id="status" name="status">
                        <option value="">{{ __('ui.common.all') }}</option>
                        @foreach($statuses as $statusValue)
                            <option value="{{ $statusValue }}" @selected($status === $statusValue)>{{ __('admin.orders.statuses.'.$statusValue) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="align-self: end; margin-bottom: 0;">
                    <button class="button" type="submit">{{ __('admin.buttons.search') }}</button>
                </div>
            </div>
        </form>

        <div class="list">
            @forelse($orders as $order)
                @php($payment = $order->latestPayment)
                <div class="item" style="display:block;">
                    <div class="thread-main" style="justify-content: space-between; gap: 16px; align-items:flex-start;">
                        <div class="meta-stack">
                            <strong>{{ $order->order_number }}</strong>
                            <span class="muted">{{ $order->user?->username }} &bull; {{ $order->plan?->name }}</span>
                            <span class="muted">{{ __('admin.orders.amount') }}: {{ number_format($order->amount) }} {{ $order->currency }}</span>
                            <span class="muted">{{ __('admin.orders.gateway') }}: {{ strtoupper($order->gateway) }}</span>
                            @if($payment?->track_id)
                                <span class="muted">{{ __('admin.orders.track_id') }}: {{ $payment->track_id }}</span>
                            @endif
                        </div>
                        <div class="meta-stack" style="min-width: 220px;">
                            <span class="badge">{{ __('admin.orders.statuses.'.$order->status) }}</span>
                            <span class="muted">{{ __('admin.orders.created_at') }}: {{ \App\Support\LocalizedDate::dateTime($order->created_at) }}</span>
                            <span class="muted">{{ __('admin.orders.paid_at') }}: {{ \App\Support\LocalizedDate::dateTime($order->paid_at) }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('admin.orders.empty') }}</p>
            @endforelse
        </div>

        <div style="margin-top: 16px;">
            {{ $orders->links() }}
        </div>
    </section>
@endsection

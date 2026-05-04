@extends('layouts.app')

@section('page_title', __('ui.storage.title'))

@section('content')
    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.storage.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.storage.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            @if($storageProfile['enabled'])
                <a class="button primary" href="{{ route('files.create', ['destination' => 'storage']) }}">{{ __('ui.storage.add_file') }}</a>
            @endif
            <a class="button" href="{{ route('subscriptions.upgrade') }}">{{ __('ui.dashboard.upgrade_cta') }}</a>
        </div>
    </section>

    @unless($storageProfile['enabled'])
        <div class="status" style="margin-top: 18px;">
            {{ __('ui.storage.locked_body') }}
        </div>
    @endunless

    <section class="stats-grid" style="margin-top: 18px;">
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_files') }}</span>
            <h3>{{ __('ui.storage.files_title') }}</h3>
            <strong class="value">{{ number_format($storageProfile['files_count']) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_used') }}</span>
            <h3>{{ __('ui.storage.used_title') }}</h3>
            <strong class="value">{{ number_format((int) round($storageProfile['used_bytes'] / 1024 / 1024, 1)) }} MB</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_quota') }}</span>
            <h3>{{ __('ui.storage.quota_title') }}</h3>
            <strong class="value">
                @if($storageProfile['has_unlimited_quota'])
                    {{ __('ui.storage.unlimited') }}
                @else
                    {{ number_format((int) ($storageProfile['quota_mb'] ?? 0)) }} MB
                @endif
            </strong>
        </article>
    </section>

    <section class="panel" style="margin-top: 18px;">
        <div class="section-heading">
            <div class="title-with-help">
                <h2 style="margin-bottom: 0;">{{ __('ui.storage.files_heading') }}</h2>
                @include('partials.inline-help', ['text' => __('ui.storage.files_body')])
            </div>
            @if(!$storageProfile['has_unlimited_quota'])
                <span class="badge">{{ __('ui.storage.remaining', ['size' => number_format((int) round(($storageProfile['remaining_bytes'] ?? 0) / 1024 / 1024, 1))]) }}</span>
            @endif
        </div>

        <div class="list" style="margin-top: 18px;">
            @forelse($files as $file)
                <div class="item" style="align-items: flex-start;">
                    <div style="min-width: 0;">
                        <strong>{{ $file->original_name }}</strong>
                        <div class="muted">{{ $file->readableSize() }} &bull; {{ $file->created_at?->diffForHumans() }}</div>
                        <div class="message-statuses">
                            <span class="badge">{{ __('ui.storage.no_expiry') }}</span>
                            @if($file->isSecurityScanPending())
                                <span class="badge">{{ __('ui.statuses.security_pending') }}</span>
                            @elseif(!$file->isSecurityApproved())
                                <span class="badge">{{ __('ui.statuses.security_rejected') }}</span>
                            @else
                                <span class="badge">{{ __('ui.statuses.scan_clean') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="actions" style="justify-content: flex-end;">
                        @if(\App\Support\FilePreviewPolicy::canPreview($file, $previewPolicy))
                            <a class="button" href="{{ route('storage.preview', $file) }}">{{ __('ui.actions.open_preview') }}</a>
                        @endif
                        <a class="button" href="{{ route('storage.download', $file) }}">{{ __('ui.actions.download') }}</a>
                        <form method="post" action="{{ route('storage.destroy', $file) }}">
                            @csrf
                            @method('delete')
                            <button class="button" type="submit">{{ __('ui.common.delete') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <span class="empty-icon">&bull;</span>
                    <strong>{{ __('ui.storage.empty_title') }}</strong>
                    <div class="muted">{{ __('ui.storage.empty_body') }}</div>
                </div>
            @endforelse
        </div>
    </section>
@endsection

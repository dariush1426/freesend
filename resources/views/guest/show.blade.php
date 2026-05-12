@extends('layouts.app')

@section('page_title', __('ui.guest_received.title'))

@section('content')
    @php
        $previewType = \App\Support\FilePreviewPolicy::detectType($file, $previewPolicy);
        $canPreviewByPolicy = \App\Support\FilePreviewPolicy::canPreview($file, $previewPolicy);
        $isUnlocked = in_array($file->id, $unlockedFileIds ?? [], true);
        $canPreviewNow = $canPreviewByPolicy && (! $file->isPasswordProtected() || $isUnlocked);
        $isExpiredByTime = $file->expires_at && $file->expires_at->isPast();
        $isDownloadable = $file->status === \App\Models\SharedFile::STATUS_ACTIVE && ! $isExpiredByTime;
    @endphp

    <section class="page-hero" style="margin-bottom: 18px;">
        <div class="title-with-help">
            <h1>{{ __('ui.guest_received.heading') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.guest_received.body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            @if($isDownloadable)
                <a class="button primary" href="{{ route('file-sends.download', $fileSend) }}">{{ __('ui.guest_received.download') }}</a>
            @endif
            <a class="button" href="{{ route('dashboard') }}">{{ __('ui.guest_received.back_dashboard') }}</a>
            <a class="button" href="{{ route('history.index', ['direction' => 'received']) }}">{{ __('ui.guest_received.back_history') }}</a>
        </div>
    </section>

    <section class="messenger-layout three-columns">
        <aside class="wizard-sidebar">
            <div class="panel">
                <div class="list">
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.guest_received.sender') }}</strong>
                            <div class="muted">{{ $fileSend->senderDisplayName() }}</div>
                        </div>
                        <span class="badge">{{ __('ui.guest_received.badge') }}</span>
                    </div>
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.guest_received.contact') }}</strong>
                            <div class="muted">{{ $fileSend->senderDisplayContact() ?: __('ui.common.not_available') }}</div>
                        </div>
                        <span class="badge">{{ __('ui.common.info') }}</span>
                    </div>
                    <div class="item">
                        <div>
                            <strong>{{ __('ui.exchange.expires_at') }}</strong>
                            <div class="muted">{{ \App\Support\LocalizedDate::dateTime($file->expires_at) }}</div>
                        </div>
                        <span class="badge">{{ $file->isDownloadable() ? __('ui.common.active') : __('ui.statuses.expired') }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <section class="panel">
            <div class="section-block">
                <div class="section-heading">
                    <div>
                        <h3>{{ $file->original_name }}</h3>
                        <p class="muted">{{ $file->readableSize() }} · {{ __('ui.file_types.'.$file->category()) }}</p>
                    </div>
                </div>

                <div class="exchange-card">
                    @include('partials.file-preview-card', ['send' => $fileSend, 'previewPolicy' => $previewPolicy, 'unlockedFileIds' => $unlockedFileIds])
                    <div class="exchange-card-main">
                        <div class="message-statuses">
                            @if($fileSend->downloaded_at)
                                <span class="badge">{{ __('ui.statuses.downloaded') }}</span>
                            @endif
                            @if($file->status === \App\Models\SharedFile::STATUS_EXPIRED || $isExpiredByTime)
                                <span class="badge">{{ __('ui.statuses.expired') }}</span>
                            @endif
                            @if($file->status === \App\Models\SharedFile::STATUS_DELETED)
                                <span class="badge">{{ __('ui.statuses.deleted') }}</span>
                            @endif
                            @if($file->isSecurityScanPending())
                                <span class="badge">{{ __('ui.statuses.security_pending') }}</span>
                            @elseif(! $file->isSecurityApproved())
                                <span class="badge">{{ __('ui.statuses.security_rejected') }}</span>
                            @endif
                        </div>
                        @if($previewType === 'other')
                            <div class="muted">{{ __('ui.exchange.preview_not_available') }}</div>
                        @elseif(! $canPreviewByPolicy)
                            <div class="muted">{{ __('ui.exchange.preview_policy_off') }}</div>
                        @elseif($file->isPasswordProtected() && ! $isUnlocked)
                            <div class="muted">{{ __('ui.exchange.password_required_preview') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="section-block">
                <div class="section-heading">
                    <div>
                        <h3>{{ __('ui.guest_received.message') }}</h3>
                    </div>
                </div>
                <p class="muted" style="margin: 0;">{{ $fileSend->message ?: __('ui.guest_received.empty_message') }}</p>
            </div>

            <div class="action-bar">
                @if($isDownloadable)
                    <a class="button primary" href="{{ route('file-sends.download', $fileSend) }}">{{ __('ui.guest_received.download') }}</a>
                @endif
                @if($canPreviewNow)
                    <a class="button" href="{{ route('file-sends.preview', $fileSend) }}" target="_blank" rel="noopener">{{ __('ui.actions.open_preview') }}</a>
                @endif
            </div>
        </section>
    </section>
@endsection

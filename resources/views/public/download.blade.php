@extends('layouts.app')

@section('page_title', __('ui.public_download.title'))

@section('content')
    <section class="panel" style="max-width: 860px;">
        <h1 style="margin-bottom:8px;">{{ __('ui.public_download.heading') }}</h1>
        <p class="muted" style="margin-top:0;">
            {{ __('ui.public_download.sender') }}: <strong>{{ $fileSend->senderDisplayName() }}</strong>
        </p>

        <div class="list" style="margin-top: 18px;">
            <div class="item">
                <div>
                    <strong>{{ __('ui.public_download.file_name') }}</strong>
                    <div class="muted">{{ $file->original_name }}</div>
                </div>
                <span class="badge">{{ $file->extension ?: __('ui.common.no_extension') }}</span>
            </div>
            <div class="item">
                <div>
                    <strong>{{ __('ui.public_download.file_size') }}</strong>
                    <div class="muted">{{ $file->readableSize() }}</div>
                </div>
                @if($fileSend->public_max_downloads)
                    <span class="badge">{{ $fileSend->public_download_count }} / {{ $fileSend->public_max_downloads }}</span>
                @else
                    <span class="badge">{{ __('ui.public_download.download_count', ['count' => $fileSend->public_download_count]) }}</span>
                @endif
            </div>
            <div class="item">
                <div>
                    <strong>{{ __('ui.public_download.public_link_expire') }}</strong>
                    <div class="muted">
                        {{ $fileSend->public_link_expires_at ? \App\Support\LocalizedDate::dateTime($fileSend->public_link_expires_at) : __('ui.public_download.no_expiry') }}
                    </div>
                </div>
                <span class="badge">{{ $fileSend->public_link_enabled ? __('ui.common.enabled') : __('ui.common.disabled') }}</span>
            </div>
        </div>

        @if($errorText)
            <div class="errors" style="margin-top:16px;">{{ $errorText }}</div>
        @endif

        @if($needsPassword && ! $errorText)
            <div class="status" style="margin-top:16px;">
                {{ __('ui.public_download.password_notice') }}
            </div>
            <form method="post" action="{{ route('public-files.download.verify', $fileSend->public_token) }}" style="margin-top:14px;">
                @csrf
                <div class="field">
                    <label for="download_password">{{ __('ui.public_download.download_password') }}</label>
                    <input id="download_password" name="download_password" type="password" required>
                </div>
                <button class="button primary" type="submit">{{ __('ui.public_download.confirm_password') }}</button>
            </form>
        @endif

        @if($canPreview)
            @if($previewType === 'image')
                <div style="margin:16px 0;">
                    <img
                        src="{{ route('public-files.preview', $fileSend->public_token) }}"
                        alt="{{ __('ui.public_download.preview_alt') }}"
                        style="max-width:100%;max-height:360px;border:1px solid #d9e1ea;border-radius:12px;background:#fff;display:block;"
                        loading="lazy"
                    >
                </div>
            @elseif($previewType === 'pdf')
                <div style="margin:16px 0;">
                    <iframe
                        src="{{ route('public-files.preview', $fileSend->public_token) }}#toolbar=0&navpanes=0"
                        title="{{ __('ui.public_download.pdf_preview_title') }}"
                        style="width:100%;height:420px;border:1px solid #d9e1ea;border-radius:12px;background:#fff;"
                    ></iframe>
                </div>
            @endif
        @elseif(! $needsPassword && ! $errorText)
            <div class="muted" style="margin-top:16px;">{{ __('ui.public_download.preview_unavailable') }}</div>
        @endif

        <div class="action-bar" style="margin-top:18px;">
            @if($canDownload)
                <a class="button primary" href="{{ route('public-files.download.file', $fileSend->public_token) }}">{{ __('ui.public_download.download_file') }}</a>
            @else
                <span class="button" style="opacity:.7;cursor:not-allowed;">{{ __('ui.public_download.download_disabled') }}</span>
            @endif
        </div>
    </section>
@endsection

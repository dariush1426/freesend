@extends('layouts.app')

@section('page_title', __('ui.unlock.title'))

@section('content')
    @php
        $backRoute = $fileSend->sender_id === auth()->id()
            ? route('conversations.show', $fileSend->receiver)
            : ($fileSend->sender
                ? route('conversations.show', $fileSend->sender)
                : route('guest-file-sends.show', $fileSend));
    @endphp
    <section class="panel" style="max-width: 620px; margin: 0 auto;">
        <h1>{{ __('ui.unlock.heading') }}</h1>
        <p class="muted">{{ __('ui.unlock.body') }}</p>

        <div class="item" style="margin: 16px 0;">
            <div>
                <strong>{{ $file->original_name }}</strong>
                <div class="muted">{{ $file->readableSize() }} · {{ $file->extension ?: __('ui.common.no_extension') }}</div>
            </div>
            <span class="badge">{{ __('ui.unlock.protected') }}</span>
        </div>

        <form method="post" action="{{ route('file-sends.unlock.verify', $fileSend) }}">
            @csrf
            <div class="field">
                <label for="download_password">{{ __('ui.unlock.download_password') }}</label>
                <input id="download_password" name="download_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="actions">
                <button class="button primary" type="submit">{{ __('ui.unlock.submit') }}</button>
                <a class="button" href="{{ $backRoute }}">{{ __('ui.common.back') }}</a>
            </div>
        </form>
    </section>
@endsection

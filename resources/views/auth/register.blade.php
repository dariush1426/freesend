@extends('layouts.app')

@section('page_title', __('auth_ui.register.page_title'))

@section('content')
    <section class="panel auth-panel" style="max-width: 640px; margin: 0 auto;">
        <h1>{{ __('auth_ui.register.title') }}</h1>
       <!-- <p class="muted">{{ __('auth_ui.register.subtitle') }}</p> -->
        @if(($prefill['full_name'] ?? '') || ($prefill['email'] ?? '') || ($prefill['mobile'] ?? ''))
            <div class="status">{{ __('auth_ui.register.prefill_notice') }}</div>
        @endif
        <form method="post" action="{{ route('register') }}">
            @csrf
            <div class="grid cols-2">
                <div class="field">
                    <label for="username">{{ __('auth_ui.register.username') }}</label>
                    <input id="username" name="username" value="{{ old('username') }}" required>
                </div>
                <div class="field">
                    <label for="full_name">{{ __('auth_ui.register.full_name') }}</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name', $prefill['full_name'] ?? '') }}">
                </div>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="email">{{ __('auth_ui.register.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $prefill['email'] ?? '') }}" required>
                </div>
                <div class="field">
                    <label for="mobile">{{ __('auth_ui.register.mobile') }}</label>
                    <input id="mobile" name="mobile" value="{{ old('mobile', $prefill['mobile'] ?? '') }}">
                </div>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="password">{{ __('auth_ui.register.password') }}</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">{{ __('auth_ui.register.password_confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
            </div>
            <button class="button primary" type="submit">{{ __('auth_ui.register.submit') }}</button>
            <a class="button" href="{{ route('otp.register') }}" style="margin-inline-start:8px;">{{ __('auth_ui.register.otp_register') }}</a>
            @if(\App\Models\Setting::getValue('quick_send_enabled', 'true') === 'true')
                <a class="button" href="{{ route('quick-send.create') }}" style="margin-inline-start:8px;">{{ __('ui.quick_send.title') }}</a>
            @endif
        </form>
    </section>
@endsection

@extends('layouts.app')

@section('page_title', __('auth_ui.login.page_title'))

@section('content')
    <section class="panel auth-panel" style="max-width: 520px; margin: 0 auto;">
        <h1>{{ __('auth_ui.login.title') }}</h1>
        <p class="muted">{{ __('auth_ui.login.subtitle') }}</p>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="login">{{ __('auth_ui.login.identifier') }}</label>
                <input id="login" name="login" value="{{ old('login') }}" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">{{ __('auth_ui.login.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label style="font-weight: 400;">
                    <input style="width:auto;" type="checkbox" name="remember" value="1">
                    {{ __('auth_ui.login.remember') }}
                </label>
            </div>
            <button class="button primary" type="submit">{{ __('auth_ui.login.submit') }}</button>
            <a class="button" href="{{ route('otp.login') }}" style="margin-inline-start:8px;">{{ __('auth_ui.login.otp_login') }}</a>
            @if(\App\Models\Setting::getValue('quick_send_enabled', 'true') === 'true')
                <a class="button" href="{{ route('quick-send.create') }}" style="margin-inline-start:8px;">{{ __('ui.quick_send.title') }}</a>
            @endif
        </form>
    </section>
@endsection

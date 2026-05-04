@extends('layouts.app')

@section('page_title', __('auth_ui.otp_login.page_title'))

@section('content')
    <section class="panel" style="max-width: 560px; margin: 0 auto;">
        <h1>{{ __('auth_ui.otp_login.title') }}</h1>
        <p class="muted">{{ __('auth_ui.otp_login.subtitle') }}</p>

        <form method="post" action="{{ route('otp.login.verify') }}">
            @csrf
            <div class="field">
                <label for="mobile">{{ __('auth_ui.otp_login.mobile') }}</label>
                <input
                    id="mobile"
                    name="mobile"
                    value="{{ old('mobile', $otpMobile) }}"
                    placeholder="{{ __('auth_ui.otp_login.mobile_placeholder') }}"
                    @if($otpStep) readonly @endif
                    required
                >
            </div>

            @if($otpStep)
                <div class="field">
                    <label for="otp_code">{{ __('auth_ui.otp_login.code') }}</label>
                    <input id="otp_code" name="otp_code" value="{{ old('otp_code') }}" inputmode="numeric" required>
                </div>
                <div class="field">
                    <label style="font-weight: 400;">
                        <input style="width:auto;" type="checkbox" name="remember" value="1">
                        {{ __('auth_ui.otp_login.remember') }}
                    </label>
                </div>
                <div class="actions">
                    <button class="button primary" type="submit">{{ __('auth_ui.otp_login.submit') }}</button>
                    <button class="button" type="submit" formaction="{{ route('otp.login.request') }}">{{ __('auth_ui.otp_login.resend') }}</button>
                    <a class="button" href="{{ route('otp.login', ['reset' => 1]) }}">{{ __('auth_ui.otp_login.change_mobile') }}</a>
                </div>
            @else
                <div class="actions">
                    <button class="button primary" type="submit" formaction="{{ route('otp.login.request') }}">{{ __('auth_ui.otp_login.request') }}</button>
                    <a class="button" href="{{ route('login') }}">{{ __('auth_ui.otp_login.password_login') }}</a>
                </div>
            @endif
        </form>
    </section>
@endsection

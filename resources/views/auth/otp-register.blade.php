@extends('layouts.app')

@section('page_title', __('auth_ui.otp_register.page_title'))

@section('content')
    <section class="panel" style="max-width: 560px; margin: 0 auto;">
        <h1>{{ __('auth_ui.otp_register.title') }}</h1>
        <p class="muted">{{ __('auth_ui.otp_register.subtitle') }}</p>

        <form method="post" action="{{ $profileStep ? route('otp.register.complete') : route('otp.register.verify') }}">
            @csrf

            @if($profileStep)
                <div class="field">
                    <label for="mobile_display">{{ __('auth_ui.otp_register.mobile') }}</label>
                    <input id="mobile_display" value="{{ $otpMobile }}" readonly>
                </div>
                <p class="muted">{{ __('auth_ui.otp_register.profile_hint') }}</p>

                <div class="grid cols-2">
                    <div class="field">
                        <label for="username">{{ __('auth_ui.otp_register.username') }}</label>
                        <input id="username" name="username" value="{{ old('username') }}" required>
                    </div>
                    <div class="field">
                        <label for="full_name">{{ __('auth_ui.otp_register.full_name') }}</label>
                        <input id="full_name" name="full_name" value="{{ old('full_name') }}">
                    </div>
                </div>

                <div class="field">
                    <label for="email">{{ __('auth_ui.otp_register.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>

                <div class="grid cols-2">
                    <div class="field">
                        <label for="password">{{ __('auth_ui.otp_register.password') }}</label>
                        <input id="password" name="password" type="password" required>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">{{ __('auth_ui.otp_register.password_confirmation') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required>
                    </div>
                </div>

                <div class="actions">
                    <button class="button primary" type="submit">{{ __('auth_ui.otp_register.complete_submit') }}</button>
                    <a class="button" href="{{ route('otp.register', ['reset' => 1]) }}">{{ __('auth_ui.otp_register.change_mobile') }}</a>
                </div>
            @elseif($otpStep)
                <div class="field">
                    <label for="mobile">{{ __('auth_ui.otp_register.mobile') }}</label>
                    <input
                        id="mobile"
                        name="mobile"
                        value="{{ old('mobile', $otpMobile) }}"
                        placeholder="{{ __('auth_ui.otp_register.mobile_placeholder') }}"
                        readonly
                        required
                    >
                </div>
                <div class="field">
                    <label for="otp_code">{{ __('auth_ui.otp_register.code') }}</label>
                    <input id="otp_code" name="otp_code" value="{{ old('otp_code') }}" inputmode="numeric" required>
                </div>
                <div class="actions">
                    <button class="button primary" type="submit">{{ __('auth_ui.otp_register.verify_submit') }}</button>
                    <button class="button" type="submit" formaction="{{ route('otp.register.request') }}">{{ __('auth_ui.otp_register.resend') }}</button>
                    <a class="button" href="{{ route('otp.register', ['reset' => 1]) }}">{{ __('auth_ui.otp_register.change_mobile') }}</a>
                </div>
            @else
                <div class="field">
                    <label for="mobile">{{ __('auth_ui.otp_register.mobile') }}</label>
                    <input id="mobile" name="mobile" value="{{ old('mobile') }}" placeholder="{{ __('auth_ui.otp_register.mobile_placeholder') }}" required>
                </div>
                <div class="actions">
                    <button class="button primary" type="submit" formaction="{{ route('otp.register.request') }}">{{ __('auth_ui.otp_register.request') }}</button>
                    <a class="button" href="{{ route('register') }}">{{ __('auth_ui.otp_register.regular_register') }}</a>
                </div>
            @endif
        </form>
    </section>
@endsection

@extends('layouts.app')

@section('page_title', __('ui.profile.title'))

@section('content')
    <section class="grid cols-2">
        <div class="panel">
            <div class="thread-main" style="margin-bottom: 18px;">
                <span class="avatar large">{{ mb_substr($user->full_name ?: $user->username, 0, 1) }}</span>
                <div class="meta-stack">
                    <h1 style="margin: 0;">{{ __('ui.profile.title') }}</h1>
                    <span class="muted">{{ $user->username }}</span>
                </div>
            </div>

            <form method="post" action="{{ route('profile.update') }}">
                @csrf
                <div class="field">
                    <label for="full_name">{{ __('ui.profile.full_name') }}</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}">
                </div>
                <div class="field">
                    <label for="username">{{ __('ui.profile.username') }}</label>
                    <input id="username" name="username" value="{{ old('username', $user->username) }}" required>
                </div>
                <div class="field">
                    <label for="email">{{ __('ui.profile.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="field">
                    <label for="mobile">{{ __('ui.profile.mobile') }}</label>
                    <input id="mobile" name="mobile" value="{{ old('mobile', $user->mobile) }}">
                </div>

                <div class="actions">
                    <button class="button primary" type="submit">{{ __('ui.profile.save') }}</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>{{ __('ui.profile.security_title') }}</h2>
            <p class="muted" style="margin-top: 0; margin-bottom: 18px;">{{ __('ui.profile.verification_intro') }}</p>

            <div class="list" style="margin-bottom: 18px;">
                <div class="item" data-verification-card="email">
                    <div>
                        <strong>{{ __('ui.profile.email_status') }}</strong>
                        <div class="muted" style="margin-top: 6px;">{{ $user->email ?: __('ui.common.not_available') }}</div>
                        <div class="muted" style="margin-top: 4px;">{{ $user->email ? __('ui.profile.email_help') : __('ui.profile.verification_missing') }}</div>
                        <div class="muted" data-verification-feedback="email" style="margin-top: 8px;"></div>
                    </div>
                    <div class="actions" style="justify-content: flex-end;">
                        <span class="badge" data-verification-badge="email">{{ $user->email_verified_at ? __('ui.profile.verified') : __('ui.profile.not_verified') }}</span>
                        @if($user->email && !$user->email_verified_at)
                            <button class="button" type="button" data-request-channel="email">{{ __('ui.profile.send_code') }}</button>
                        @endif
                    </div>
                </div>
                @if($user->email && !$user->email_verified_at)
                    <div class="field" data-code-field="email" hidden style="margin-bottom: 0;">
                        <label for="email_verification_code">{{ __('ui.profile.verification_code') }}</label>
                        <div class="actions" style="align-items: stretch;">
                            <input id="email_verification_code" type="text" inputmode="numeric" maxlength="8" style="flex:1;" data-code-input="email">
                            <button class="button primary" type="button" data-verify-channel="email">{{ __('ui.profile.verify_code') }}</button>
                        </div>
                    </div>
                @endif

                <div class="item" data-verification-card="mobile">
                    <div>
                        <strong>{{ __('ui.profile.mobile_status') }}</strong>
                        <div class="muted" style="margin-top: 6px;">{{ $user->mobile ?: __('ui.common.not_available') }}</div>
                        <div class="muted" style="margin-top: 4px;">{{ $user->mobile ? __('ui.profile.mobile_help') : __('ui.profile.verification_missing') }}</div>
                        <div class="muted" data-verification-feedback="mobile" style="margin-top: 8px;"></div>
                    </div>
                    <div class="actions" style="justify-content: flex-end;">
                        <span class="badge" data-verification-badge="mobile">{{ $user->mobile_verified_at ? __('ui.profile.verified') : __('ui.profile.not_verified') }}</span>
                        @if($user->mobile && !$user->mobile_verified_at)
                            <button class="button" type="button" data-request-channel="mobile">{{ __('ui.profile.send_code') }}</button>
                        @endif
                    </div>
                </div>
                @if($user->mobile && !$user->mobile_verified_at)
                    <div class="field" data-code-field="mobile" hidden style="margin-bottom: 0;">
                        <label for="mobile_verification_code">{{ __('ui.profile.verification_code') }}</label>
                        <div class="actions" style="align-items: stretch;">
                            <input id="mobile_verification_code" type="text" inputmode="numeric" maxlength="8" style="flex:1;" data-code-input="mobile">
                            <button class="button primary" type="button" data-verify-channel="mobile">{{ __('ui.profile.verify_code') }}</button>
                        </div>
                    </div>
                @endif
            </div>

            <form method="post" action="{{ route('profile.update') }}">
                @csrf
                <input type="hidden" name="full_name" value="{{ old('full_name', $user->full_name) }}">
                <input type="hidden" name="username" value="{{ old('username', $user->username) }}">
                <input type="hidden" name="email" value="{{ old('email', $user->email) }}">
                <input type="hidden" name="mobile" value="{{ old('mobile', $user->mobile) }}">

                <div class="field">
                    <label for="current_password">{{ __('ui.profile.current_password') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="password">{{ __('ui.profile.new_password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirmation">{{ __('ui.profile.new_password_confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>

                <div class="actions">
                    <button class="button primary" type="submit">{{ __('ui.profile.save_password') }}</button>
                </div>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const csrfToken = @json(csrf_token());
            const verifiedLabel = @json(__('ui.profile.verified'));
            const resendLabel = @json(__('ui.profile.resend_code'));
            const requestRoutes = {
                email: @json(route('profile.verify.email.request')),
                mobile: @json(route('profile.verify.mobile.request')),
            };
            const verifyRoutes = {
                email: @json(route('profile.verify.email.confirm')),
                mobile: @json(route('profile.verify.mobile.confirm')),
            };

            const setFeedback = (channel, message, isError = false) => {
                const feedback = document.querySelector(`[data-verification-feedback="${channel}"]`);

                if (!feedback) {
                    return;
                }

                feedback.textContent = message || '';
                feedback.style.color = isError ? '#b42318' : '';
            };

            const setVerified = (channel) => {
                const badge = document.querySelector(`[data-verification-badge="${channel}"]`);
                const codeField = document.querySelector(`[data-code-field="${channel}"]`);
                const requestButton = document.querySelector(`[data-request-channel="${channel}"]`);
                const verifyButton = document.querySelector(`[data-verify-channel="${channel}"]`);

                if (badge) {
                    badge.textContent = verifiedLabel;
                }

                if (codeField) {
                    codeField.hidden = true;
                }

                if (requestButton) {
                    requestButton.remove();
                }

                if (verifyButton) {
                    verifyButton.remove();
                }
            };

            const sendRequest = async (url, payload) => {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    const firstError = Object.values(data.errors || {})[0];
                    const message = Array.isArray(firstError) ? firstError[0] : (data.message || '');
                    throw new Error(message || 'Request failed');
                }

                return data;
            };

            document.querySelectorAll('[data-request-channel]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const channel = button.getAttribute('data-request-channel');
                    const codeField = document.querySelector(`[data-code-field="${channel}"]`);

                    if (!channel || !requestRoutes[channel]) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const data = await sendRequest(requestRoutes[channel], {});
                        setFeedback(channel, data.message || '');

                        if (data.verified) {
                            setVerified(channel);
                            return;
                        }

                        if (codeField) {
                            codeField.hidden = false;
                        }

                        button.textContent = resendLabel;
                    } catch (error) {
                        setFeedback(channel, error instanceof Error ? error.message : '', true);
                    } finally {
                        button.disabled = false;
                    }
                });
            });

            document.querySelectorAll('[data-verify-channel]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const channel = button.getAttribute('data-verify-channel');
                    const input = document.querySelector(`[data-code-input="${channel}"]`);

                    if (!channel || !verifyRoutes[channel] || !(input instanceof HTMLInputElement)) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const data = await sendRequest(verifyRoutes[channel], {
                            code: input.value.trim(),
                        });

                        setFeedback(channel, data.message || '');

                        if (data.verified) {
                            setVerified(channel);
                        }
                    } catch (error) {
                        setFeedback(channel, error instanceof Error ? error.message : '', true);
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        })();
    </script>
@endsection

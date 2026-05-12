@php($layoutMode = 'admin')

@extends('layouts.app')

@section('page_title', __('admin.title'))

@section('content')
    <section class="page-hero" style="margin-bottom: 18px;">
        <h1>{{ __('admin.title') }}</h1>
        <p class="muted">{{ __('admin.dashboard.body') }}</p>
        <div class="hero-actions">
            <a class="button" href="{{ route('admin.dashboard') }}">{{ __('admin.nav.dashboard') }}</a>
        </div>
    </section>

    <section class="panel" style="max-width: 940px;">
        <h1>{{ __('admin.title') }}</h1>
        <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf

            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.general') }}</h2>
            <div class="grid cols-2">
                <div class="field">
                    <label for="max_file_size_mb">{{ __('admin.fields.max_file_size_mb') }}</label>
                    <input id="max_file_size_mb" name="max_file_size_mb" type="number" min="1" max="200" value="{{ $settings['max_file_size_mb'] }}">
                </div>
                <div class="field">
                    <label for="default_expire_hours">{{ __('admin.fields.default_expire_hours') }}</label>
                    <input id="default_expire_hours" name="default_expire_hours" type="number" min="1" max="720" value="{{ $settings['default_expire_hours'] }}">
                </div>
            </div>
            <div class="field">
                <label for="allowed_extensions">{{ __('admin.fields.allowed_extensions') }}</label>
                <input id="allowed_extensions" name="allowed_extensions" value="{{ $settings['allowed_extensions'] }}">
            </div>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="email_notification_enabled" value="1" @checked($settings['email_notification_enabled'] === 'true')> {{ __('admin.toggles.email_notification_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="sms_otp_enabled" value="1" @checked($settings['sms_otp_enabled'] === 'true')> {{ __('admin.toggles.sms_otp_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="sms_notification_enabled" value="1" @checked($settings['sms_notification_enabled'] === 'true')> {{ __('admin.toggles.sms_notification_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="public_link_enabled" value="1" @checked($settings['public_link_enabled'] === 'true')> {{ __('admin.toggles.public_link_enabled') }}</label>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.quick_send') }}</h2>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="quick_send_enabled" value="1" @checked($settings['quick_send_enabled'] === 'true')> {{ __('admin.toggles.quick_send_enabled') }}</label>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="quick_send_max_file_size_mb">{{ __('admin.fields.quick_send_max_file_size_mb') }}</label>
                    <input id="quick_send_max_file_size_mb" name="quick_send_max_file_size_mb" type="number" min="1" max="100" value="{{ $settings['quick_send_max_file_size_mb'] }}">
                </div>
                <div class="field">
                    <label for="quick_send_default_expire_hours">{{ __('admin.fields.quick_send_default_expire_hours') }}</label>
                    <input id="quick_send_default_expire_hours" name="quick_send_default_expire_hours" type="number" min="1" max="72" value="{{ $settings['quick_send_default_expire_hours'] }}">
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.pwa') }}</h2>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="pwa_enabled" value="1" @checked($settings['pwa_enabled'] === 'true')> {{ __('admin.toggles.pwa_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="pwa_install_popup_enabled" value="1" @checked($settings['pwa_install_popup_enabled'] === 'true')> {{ __('admin.toggles.pwa_install_popup_enabled') }}</label>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="app_display_name">{{ __('admin.fields.app_display_name') }}</label>
                    <input id="app_display_name" name="app_display_name" value="{{ $settings['app_display_name'] }}">
                </div>
                <div class="field">
                    <label for="app_short_name">{{ __('admin.fields.app_short_name') }}</label>
                    <input id="app_short_name" name="app_short_name" value="{{ $settings['app_short_name'] }}">
                </div>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="pwa_theme_color">{{ __('admin.fields.pwa_theme_color') }}</label>
                    <input id="pwa_theme_color" name="pwa_theme_color" value="{{ $settings['pwa_theme_color'] }}">
                </div>
                <div class="field">
                    <label for="pwa_background_color">{{ __('admin.fields.pwa_background_color') }}</label>
                    <input id="pwa_background_color" name="pwa_background_color" value="{{ $settings['pwa_background_color'] }}">
                </div>
            </div>
            <div class="grid cols-3">
                <div class="field">
                    <label for="pwa_logo_mobile">{{ __('admin.fields.pwa_logo_mobile') }}</label>
                    <input id="pwa_logo_mobile" name="pwa_logo_mobile" type="file" accept="image/*">
                    @if(!empty($settings['pwa_logo_mobile_path']))
                        <img src="{{ route('pwa.logo', 'mobile') }}" alt="{{ __('admin.fields.pwa_logo_mobile') }}" style="margin-top:8px;width:64px;height:64px;border-radius:12px;border:1px solid #d9e1ea;object-fit:cover;">
                    @endif
                </div>
                <div class="field">
                    <label for="pwa_logo_desktop">{{ __('admin.fields.pwa_logo_desktop') }}</label>
                    <input id="pwa_logo_desktop" name="pwa_logo_desktop" type="file" accept="image/*">
                    @if(!empty($settings['pwa_logo_desktop_path']))
                        <img src="{{ route('pwa.logo', 'desktop') }}" alt="{{ __('admin.fields.pwa_logo_desktop') }}" style="margin-top:8px;width:64px;height:64px;border-radius:12px;border:1px solid #d9e1ea;object-fit:cover;">
                    @endif
                </div>
                <div class="field">
                    <label for="pwa_logo_retina">{{ __('admin.fields.pwa_logo_retina') }}</label>
                    <input id="pwa_logo_retina" name="pwa_logo_retina" type="file" accept="image/*">
                    @if(!empty($settings['pwa_logo_retina_path']))
                        <img src="{{ route('pwa.logo', 'retina') }}" alt="{{ __('admin.fields.pwa_logo_retina') }}" style="margin-top:8px;width:64px;height:64px;border-radius:12px;border:1px solid #d9e1ea;object-fit:cover;">
                    @endif
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">تنظیمات لندینگ صفحه اصلی</h2>
            <p class="muted" style="margin-top:0;">متن ها، تصاویر و وزن/اندازه تیترهای بخش معرفی FreeSend را از اینجا تغییر دهید.</p>
            <div class="grid cols-2">
                <div class="field">
                    <label for="landing_hero_badge">برچسب بالای عنوان</label>
                    <input id="landing_hero_badge" name="landing_hero_badge" value="{{ $settings['landing_hero_badge'] }}">
                </div>
                <div class="field">
                    <label for="landing_hero_heading_tag">نوع تیتر اصلی</label>
                    <select id="landing_hero_heading_tag" name="landing_hero_heading_tag" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        <option value="h1" @selected($settings['landing_hero_heading_tag'] === 'h1')>H1</option>
                        <option value="h2" @selected($settings['landing_hero_heading_tag'] === 'h2')>H2</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="landing_hero_title">عنوان اصلی</label>
                <input id="landing_hero_title" name="landing_hero_title" value="{{ $settings['landing_hero_title'] }}">
            </div>
            <div class="field">
                <label for="landing_hero_body">متن معرفی کوتاه</label>
                <textarea id="landing_hero_body" name="landing_hero_body" rows="3">{{ $settings['landing_hero_body'] }}</textarea>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="landing_primary_cta">متن دکمه اصلی</label>
                    <input id="landing_primary_cta" name="landing_primary_cta" value="{{ $settings['landing_primary_cta'] }}">
                </div>
                <div class="field">
                    <label for="landing_secondary_cta">متن دکمه دوم</label>
                    <input id="landing_secondary_cta" name="landing_secondary_cta" value="{{ $settings['landing_secondary_cta'] }}">
                </div>
            </div>
            <div class="grid cols-3">
                <div class="field">
                    <label for="landing_hero_title_size">اندازه تیتر اصلی (px)</label>
                    <input id="landing_hero_title_size" name="landing_hero_title_size" type="number" min="28" max="72" value="{{ $settings['landing_hero_title_size'] }}">
                </div>
                <div class="field">
                    <label for="landing_hero_title_weight">وزن تیتر اصلی</label>
                    <select id="landing_hero_title_weight" name="landing_hero_title_weight" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        @foreach([500, 600, 700, 800, 900] as $weight)
                            <option value="{{ $weight }}" @selected((int) $settings['landing_hero_title_weight'] === $weight)>{{ $weight }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="landing_hero_image">تصویر بخش اصلی</label>
                    <input id="landing_hero_image" name="landing_hero_image" type="file" accept="image/*">
                    @if(!empty($settings['landing_hero_image_path']))
                        <img src="{{ route('landing.asset', 'hero') }}" alt="Landing hero" style="margin-top:8px;width:96px;height:64px;border-radius:8px;border:1px solid #d9e1ea;object-fit:cover;">
                    @endif
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="landing_features_title">عنوان بخش فیچرها</label>
                    <input id="landing_features_title" name="landing_features_title" value="{{ $settings['landing_features_title'] }}">
                </div>
                <div class="field">
                    <label for="landing_features_body">توضیح بخش فیچرها</label>
                    <textarea id="landing_features_body" name="landing_features_body" rows="2">{{ $settings['landing_features_body'] }}</textarea>
                </div>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="landing_section_title_size">اندازه تیترهای بخش ها (px)</label>
                    <input id="landing_section_title_size" name="landing_section_title_size" type="number" min="22" max="48" value="{{ $settings['landing_section_title_size'] }}">
                </div>
                <div class="field">
                    <label for="landing_section_title_weight">وزن تیترهای بخش ها</label>
                    <select id="landing_section_title_weight" name="landing_section_title_weight" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        @foreach([500, 600, 700, 800, 900] as $weight)
                            <option value="{{ $weight }}" @selected((int) $settings['landing_section_title_weight'] === $weight)>{{ $weight }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @for($featureNumber = 1; $featureNumber <= 3; $featureNumber++)
                <div class="panel" style="margin:14px 0;padding:14px;">
                    <h3 style="margin-top:0;">فیچر {{ $featureNumber }}</h3>
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="landing_feature_{{ $featureNumber }}_title">عنوان فیچر</label>
                            <input id="landing_feature_{{ $featureNumber }}_title" name="landing_feature_{{ $featureNumber }}_title" value="{{ $settings['landing_feature_'.$featureNumber.'_title'] }}">
                        </div>
                        <div class="field">
                            <label for="landing_feature_{{ $featureNumber }}_image">تصویر فیچر</label>
                            <input id="landing_feature_{{ $featureNumber }}_image" name="landing_feature_{{ $featureNumber }}_image" type="file" accept="image/*">
                            @if(!empty($settings['landing_feature_'.$featureNumber.'_image_path']))
                                <img src="{{ route('landing.asset', 'feature-'.$featureNumber) }}" alt="Landing feature {{ $featureNumber }}" style="margin-top:8px;width:96px;height:64px;border-radius:8px;border:1px solid #d9e1ea;object-fit:cover;">
                            @endif
                        </div>
                    </div>
                    <div class="field">
                        <label for="landing_feature_{{ $featureNumber }}_body">متن فیچر</label>
                        <textarea id="landing_feature_{{ $featureNumber }}_body" name="landing_feature_{{ $featureNumber }}_body" rows="2">{{ $settings['landing_feature_'.$featureNumber.'_body'] }}</textarea>
                    </div>
                </div>
            @endfor

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.mail') }}</h2>

            <div class="grid cols-2">
                <div class="field">
                    <label for="mail_mailer">{{ __('admin.fields.mail_mailer') }}</label>
                    <select id="mail_mailer" name="mail_mailer" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        @foreach($mailerOptions as $value => $label)
                            <option value="{{ $value }}" @selected($settings['mail_mailer'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="mail_encryption">{{ __('admin.fields.mail_encryption') }}</label>
                    <select id="mail_encryption" name="mail_encryption" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        @foreach($encryptionOptions as $value => $label)
                            <option value="{{ $value }}" @selected($settings['mail_encryption'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="mail_host">{{ __('admin.fields.mail_host') }}</label>
                    <input id="mail_host" name="mail_host" value="{{ $settings['mail_host'] }}">
                </div>
                <div class="field">
                    <label for="mail_port">{{ __('admin.fields.mail_port') }}</label>
                    <input id="mail_port" name="mail_port" type="number" min="1" max="65535" value="{{ $settings['mail_port'] }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="mail_username">{{ __('admin.fields.mail_username') }}</label>
                    <input id="mail_username" name="mail_username" value="{{ $settings['mail_username'] }}">
                </div>
                <div class="field">
                    <label for="mail_password">{{ __('admin.fields.mail_password') }}</label>
                    <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" placeholder="{{ __('admin.hints.mail_password') }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="mail_from_address">{{ __('admin.fields.mail_from_address') }}</label>
                    <input id="mail_from_address" name="mail_from_address" type="email" value="{{ $settings['mail_from_address'] }}">
                </div>
                <div class="field">
                    <label for="mail_from_name">{{ __('admin.fields.mail_from_name') }}</label>
                    <input id="mail_from_name" name="mail_from_name" value="{{ $settings['mail_from_name'] }}">
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.payment') }}</h2>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="zibal_enabled" value="1" @checked($settings['zibal_enabled'] === 'true')> {{ __('admin.toggles.zibal_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="zibal_test_mode" value="1" @checked($settings['zibal_test_mode'] === 'true')> {{ __('admin.toggles.zibal_test_mode') }}</label>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="zibal_merchant">{{ __('admin.fields.zibal_merchant') }}</label>
                    <input id="zibal_merchant" name="zibal_merchant" value="{{ $settings['zibal_merchant'] }}">
                    <small class="muted">{{ __('admin.hints.zibal_merchant') }}</small>
                </div>
                <div class="field">
                    <label for="zibal_request_url">{{ __('admin.fields.zibal_request_url') }}</label>
                    <input id="zibal_request_url" name="zibal_request_url" value="{{ $settings['zibal_request_url'] }}">
                </div>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="zibal_start_url">{{ __('admin.fields.zibal_start_url') }}</label>
                    <input id="zibal_start_url" name="zibal_start_url" value="{{ $settings['zibal_start_url'] }}">
                </div>
                <div class="field">
                    <label for="zibal_verify_url">{{ __('admin.fields.zibal_verify_url') }}</label>
                    <input id="zibal_verify_url" name="zibal_verify_url" value="{{ $settings['zibal_verify_url'] }}">
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.sms') }}</h2>

            <div class="grid cols-2">
                <div class="field">
                    <label for="sms_driver">{{ __('admin.fields.sms_driver') }}</label>
                    <select id="sms_driver" name="sms_driver" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        @foreach($smsDriverOptions as $value => $label)
                            <option value="{{ $value }}" @selected($settings['sms_driver'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="sms_otp_ttl_minutes">{{ __('admin.fields.sms_otp_ttl_minutes') }}</label>
                    <input id="sms_otp_ttl_minutes" name="sms_otp_ttl_minutes" type="number" min="1" max="30" value="{{ $settings['sms_otp_ttl_minutes'] }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="sms_otp_length">{{ __('admin.fields.sms_otp_length') }}</label>
                    <input id="sms_otp_length" name="sms_otp_length" type="number" min="4" max="8" value="{{ $settings['sms_otp_length'] }}">
                </div>
                <div class="field">
                    <label for="sms_otp_max_attempts">{{ __('admin.fields.sms_otp_max_attempts') }}</label>
                    <input id="sms_otp_max_attempts" name="sms_otp_max_attempts" type="number" min="1" max="10" value="{{ $settings['sms_otp_max_attempts'] }}">
                </div>
            </div>

            <div class="field">
                <label for="sms_otp_resend_seconds">{{ __('admin.fields.sms_otp_resend_seconds') }}</label>
                <input id="sms_otp_resend_seconds" name="sms_otp_resend_seconds" type="number" min="15" max="600" value="{{ $settings['sms_otp_resend_seconds'] }}">
            </div>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="sms_ssl_verify" value="1" @checked($settings['sms_ssl_verify'] === 'true')> {{ __('admin.toggles.sms_ssl_verify') }}</label>
            </div>
            <div class="field">
                <label for="sms_ca_bundle_path">{{ __('admin.fields.sms_ca_bundle_path') }}</label>
                <input id="sms_ca_bundle_path" name="sms_ca_bundle_path" placeholder="C:\wamp64\bin\php\php8.3.14\extras\ssl\cacert.pem" value="{{ $settings['sms_ca_bundle_path'] }}">
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="smsir_base_url">{{ __('admin.fields.smsir_base_url') }}</label>
                    <input id="smsir_base_url" name="smsir_base_url" value="{{ $settings['smsir_base_url'] }}">
                </div>
                <div class="field">
                    <label for="smsir_api_key">{{ __('admin.fields.smsir_api_key') }}</label>
                    <input id="smsir_api_key" name="smsir_api_key" value="{{ $settings['smsir_api_key'] }}">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="smsir_otp_template_id">{{ __('admin.fields.smsir_otp_template_id') }}</label>
                    <input id="smsir_otp_template_id" name="smsir_otp_template_id" value="{{ $settings['smsir_otp_template_id'] }}">
                </div>
                <div class="field">
                    <label for="smsir_otp_parameter_name">{{ __('admin.fields.smsir_otp_parameter_name') }}</label>
                    <input id="smsir_otp_parameter_name" name="smsir_otp_parameter_name" value="{{ $settings['smsir_otp_parameter_name'] }}">
                </div>
            </div>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="smsir_otp_fallback_enabled" value="1" @checked($settings['smsir_otp_fallback_enabled'] === 'true')> {{ __('admin.toggles.smsir_otp_fallback_enabled') }}</label>
            </div>
            <div class="field">
                <label for="sms_otp_message_template">{{ __('admin.fields.sms_otp_message_template') }}</label>
                <input id="sms_otp_message_template" name="sms_otp_message_template" value="{{ $settings['sms_otp_message_template'] }}">
                <small class="muted">{{ __('admin.hints.otp_template') }}</small>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="smsir_line_number">{{ __('admin.fields.smsir_line_number') }}</label>
                    <input id="smsir_line_number" name="smsir_line_number" value="{{ $settings['smsir_line_number'] }}">
                </div>
                <div class="field">
                    <label for="smsir_verify_endpoint">{{ __('admin.fields.smsir_verify_endpoint') }}</label>
                    <input id="smsir_verify_endpoint" name="smsir_verify_endpoint" value="{{ $settings['smsir_verify_endpoint'] }}">
                </div>
            </div>

            <div class="field">
                <label for="smsir_message_endpoint">{{ __('admin.fields.smsir_message_endpoint') }}</label>
                <input id="smsir_message_endpoint" name="smsir_message_endpoint" value="{{ $settings['smsir_message_endpoint'] }}">
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.preview') }}</h2>

            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="preview_enabled" value="1" @checked($settings['preview_enabled'] === 'true')> {{ __('admin.toggles.preview_enabled') }}</label>
                <label><input style="width:auto;" type="checkbox" name="preview_pdf_enabled" value="1" @checked($settings['preview_pdf_enabled'] === 'true')> {{ __('admin.toggles.preview_pdf_enabled') }}</label>
            </div>

            <div class="grid cols-2">
                <div class="field">
                    <label for="preview_max_size_mb">{{ __('admin.fields.preview_max_size_mb') }}</label>
                    <input id="preview_max_size_mb" name="preview_max_size_mb" type="number" min="1" max="100" value="{{ $settings['preview_max_size_mb'] }}">
                </div>
                <div class="field">
                    <label for="preview_image_extensions">{{ __('admin.fields.preview_image_extensions') }}</label>
                    <input id="preview_image_extensions" name="preview_image_extensions" value="{{ $settings['preview_image_extensions'] }}">
                    <small class="muted">{!! __('admin.hints.preview_image_extensions') !!}</small>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.chunk') }}</h2>
            <div class="grid cols-2">
                <div class="field">
                    <label for="chunk_upload_threshold_mb">{{ __('admin.fields.chunk_upload_threshold_mb') }}</label>
                    <input id="chunk_upload_threshold_mb" name="chunk_upload_threshold_mb" type="number" min="1" max="1024" value="{{ $settings['chunk_upload_threshold_mb'] }}">
                </div>
                <div class="field">
                    <label for="chunk_upload_size_mb">{{ __('admin.fields.chunk_upload_size_mb') }}</label>
                    <input id="chunk_upload_size_mb" name="chunk_upload_size_mb" type="number" min="1" max="20" value="{{ $settings['chunk_upload_size_mb'] }}">
                </div>
            </div>
            <div class="field">
                <label for="chunk_upload_max_mb_per_minute">{{ __('admin.fields.chunk_upload_max_mb_per_minute') }}</label>
                <input id="chunk_upload_max_mb_per_minute" name="chunk_upload_max_mb_per_minute" type="number" min="1" max="5000" value="{{ $settings['chunk_upload_max_mb_per_minute'] }}">
            </div>

            <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
            <h2 style="margin-bottom: 8px;">{{ __('admin.sections.security') }}</h2>
            <div class="field">
                <label><input style="width:auto;" type="checkbox" name="security_scan_enabled" value="1" @checked($settings['security_scan_enabled'] === 'true')> {{ __('admin.toggles.security_scan_enabled') }}</label>
            </div>
            <div class="grid cols-2">
                <div class="field">
                    <label for="security_scan_driver">{{ __('admin.fields.security_scan_driver') }}</label>
                    <select id="security_scan_driver" name="security_scan_driver" style="width:100%;border:1px solid #d9e1ea;border-radius:14px;padding:12px 14px;background:#fff;">
                        <option value="basic" @selected($settings['security_scan_driver'] === 'basic')>{{ __('admin.options.security_driver.basic') }}</option>
                    </select>
                </div>
                <div class="field">
                    <label for="security_blocked_extensions">{{ __('admin.fields.security_blocked_extensions') }}</label>
                    <input id="security_blocked_extensions" name="security_blocked_extensions" value="{{ $settings['security_blocked_extensions'] }}">
                    <small class="muted">{!! __('admin.hints.security_blocked_extensions') !!}</small>
                </div>
            </div>

            <button class="button primary" type="submit">{{ __('admin.save') }}</button>
        </form>

        <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.sections.test_email') }}</h2>
        <form method="post" action="{{ route('admin.settings.test-email') }}">
            @csrf
            <div class="field">
                <label for="test_email">{{ __('admin.fields.test_email') }}</label>
                <input id="test_email" name="test_email" type="email" placeholder="example@domain.com" value="{{ old('test_email', auth()->user()->email) }}">
            </div>
            <button class="button" type="submit">{{ __('admin.buttons.send_test_email') }}</button>
        </form>

        <hr style="border:0;border-top:1px solid #d9e1ea;margin:22px 0;">
        <h2 style="margin-bottom: 8px;">{{ __('admin.sections.test_sms') }}</h2>
        <form method="post" action="{{ route('admin.settings.test-sms') }}">
            @csrf
            <div class="field">
                <label for="test_sms_mobile">{{ __('admin.fields.test_sms_mobile') }}</label>
                <input id="test_sms_mobile" name="test_sms_mobile" placeholder="0912xxxxxxx" value="{{ old('test_sms_mobile', auth()->user()->mobile) }}">
            </div>
            <button class="button" type="submit">{{ __('admin.buttons.send_test_sms') }}</button>
        </form>
    </section>
@endsection

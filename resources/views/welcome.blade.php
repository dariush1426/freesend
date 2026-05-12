@php
    $heroTag = in_array($landing['landing_hero_heading_tag'] ?? 'h2', ['h1', 'h2'], true)
        ? $landing['landing_hero_heading_tag']
        : 'h2';
    $heroTitleSize = max(28, min(72, (int) ($landing['landing_hero_title_size'] ?? 44)));
    $heroTitleWeight = in_array((int) ($landing['landing_hero_title_weight'] ?? 800), [500, 600, 700, 800, 900], true)
        ? (int) $landing['landing_hero_title_weight']
        : 800;
    $sectionTitleSize = max(22, min(48, (int) ($landing['landing_section_title_size'] ?? 32)));
    $sectionTitleWeight = in_array((int) ($landing['landing_section_title_weight'] ?? 800), [500, 600, 700, 800, 900], true)
        ? (int) $landing['landing_section_title_weight']
        : 800;
    $heroImagePath = (string) ($landing['landing_hero_image_path'] ?? '');
    $heroImageUrl = $heroImagePath !== '' ? route('landing.asset', 'hero') : '';
    $features = collect([1, 2, 3])->map(fn (int $number): array => [
        'image_url' => ($landing['landing_feature_'.$number.'_image_path'] ?? '') !== '' ? route('landing.asset', 'feature-'.$number) : '',
        'number' => $number,
        'title' => $landing['landing_feature_'.$number.'_title'] ?? '',
        'body' => $landing['landing_feature_'.$number.'_body'] ?? '',
        'image_path' => $landing['landing_feature_'.$number.'_image_path'] ?? '',
    ])->all();
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts/vazirmatn/vazirmatn.css') }}?v=20260430">
    <style>
        :root {
            --bg: #f6f8fb;
            --ink: #162033;
            --muted: #5f6f85;
            --line: #dde5ef;
            --panel: #ffffff;
            --primary: #0f766e;
            --primary-strong: #115e59;
            --soft: #e8f5f1;
            --hero-title-size: {{ $heroTitleSize }}px;
            --hero-title-weight: {{ $heroTitleWeight }};
            --section-title-size: {{ $sectionTitleSize }}px;
            --section-title-weight: {{ $sectionTitleWeight }};
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            line-height: 1.75;
        }
        a { color: inherit; text-decoration: none; }
        .nav {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px clamp(18px, 5vw, 76px);
            background: rgba(255, 255, 255, .88);
            border-bottom: 1px solid rgba(221, 229, 239, .9);
            backdrop-filter: blur(12px);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 900;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--soft);
            color: var(--primary-strong);
            border: 1px solid rgba(15, 118, 110, .15);
            font-size: 14px;
        }
        .nav-actions, .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .button {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 9px 15px;
            background: #fff;
            color: var(--ink);
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
        }
        .button.primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .hero {
            min-height: calc(100vh - 70px);
            display: grid;
            align-items: end;
            padding: clamp(36px, 7vw, 86px) clamp(18px, 5vw, 76px);
            background:
                linear-gradient(90deg, rgba(246, 248, 251, .98), rgba(246, 248, 251, .76) 48%, rgba(246, 248, 251, .26)),
                linear-gradient(135deg, #ffffff 0%, #edf4f4 100%);
            overflow: hidden;
        }
        @if($heroImageUrl !== '')
            .hero {
                background:
                    linear-gradient(90deg, rgba(246, 248, 251, .98), rgba(246, 248, 251, .78) 48%, rgba(246, 248, 251, .2)),
                    url("{{ $heroImageUrl }}") center / cover no-repeat;
            }
        @endif
        .hero-inner {
            max-width: 760px;
            padding-bottom: clamp(18px, 5vh, 52px);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 4px 11px;
            border-radius: 999px;
            background: rgba(15, 118, 110, .1);
            color: var(--primary-strong);
            font-size: 12px;
            font-weight: 900;
        }
        .hero-title {
            margin: 16px 0 0;
            font-size: var(--hero-title-size);
            font-weight: var(--hero-title-weight);
            line-height: 1.16;
            letter-spacing: 0;
            max-width: 820px;
        }
        .hero-copy {
            margin: 18px 0 0;
            max-width: 680px;
            color: var(--muted);
            font-size: 18px;
        }
        .actions { margin-top: 24px; }
        .product-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--line);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }
        .metric {
            min-height: 116px;
            padding: 20px clamp(16px, 4vw, 34px);
            background: #fff;
        }
        .metric strong {
            display: block;
            font-size: 22px;
            line-height: 1.35;
        }
        .metric span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
        }
        .section {
            padding: clamp(38px, 6vw, 78px) clamp(18px, 5vw, 76px);
        }
        .section-heading {
            max-width: 760px;
            margin-bottom: 24px;
        }
        .section-heading h2 {
            margin: 0 0 8px;
            font-size: var(--section-title-size);
            font-weight: var(--section-title-weight);
            line-height: 1.25;
        }
        .muted { color: var(--muted); font-size: 14px; }
        .feature-grid, .plans {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .feature, .plan {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }
        .feature-media {
            min-height: 146px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ecfdf5, #eff6ff);
            color: var(--primary-strong);
            font-size: 34px;
            font-weight: 900;
        }
        .feature-media img {
            width: 100%;
            height: 176px;
            object-fit: cover;
            display: block;
        }
        .feature-body, .plan {
            padding: 18px;
        }
        .feature h3, .plan h3 {
            margin: 0 0 8px;
            font-size: 20px;
            line-height: 1.4;
        }
        .plans {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .plan {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .plan-price {
            font-size: 26px;
            font-weight: 900;
        }
        .launch-note {
            width: fit-content;
            color: #92400e;
            background: rgba(245, 158, 11, .12);
        }
        footer {
            padding: 24px clamp(18px, 5vw, 76px);
            border-top: 1px solid var(--line);
            background: #fff;
            color: var(--muted);
        }
        @media (max-width: 860px) {
            .nav { align-items: flex-start; flex-direction: column; }
            .hero { min-height: auto; padding-top: 44px; }
            .hero-title { font-size: min(var(--hero-title-size), 38px); }
            .hero-copy { font-size: 16px; }
            .product-strip, .feature-grid { grid-template-columns: 1fr; }
            .metric { min-height: auto; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a class="brand" href="{{ route('login') }}" aria-label="{{ $appName }}">
            <span class="brand-mark">FS</span>
            <span>{{ $appName }}</span>
        </a>
        <div class="nav-actions">
            @if($quickSendEnabled)
                <a class="button" href="{{ route('quick-send.create') }}">{{ __('ui.quick_send.title') }}</a>
            @endif
            <a class="button" href="{{ route('login') }}">{{ __('auth_ui.login.submit') }}</a>
            <a class="button primary" href="{{ route('register') }}">{{ __('auth_ui.register.submit') }}</a>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="hero-inner">
                <span class="badge">{{ $landing['landing_hero_badge'] }}</span>
                @if($heroTag === 'h1')
                    <h1 class="hero-title">{{ $landing['landing_hero_title'] }}</h1>
                @else
                    <h2 class="hero-title">{{ $landing['landing_hero_title'] }}</h2>
                @endif
                <p class="hero-copy">{{ $landing['landing_hero_body'] }}</p>
                <div class="actions">
                    @if($quickSendEnabled)
                        <a class="button primary" href="{{ route('quick-send.create') }}">{{ $landing['landing_primary_cta'] }}</a>
                    @endif
                    <a class="button" href="{{ route('register') }}">{{ $landing['landing_secondary_cta'] }}</a>
                </div>
            </div>
        </section>

        <section class="product-strip" aria-label="{{ __('ui.landing.preview_label') }}">
            <div class="metric">
                <strong>{{ __('ui.landing.preview_file_one') }}</strong>
                <span>{{ __('ui.landing.preview_file_one_meta') }}</span>
            </div>
            <div class="metric">
                <strong>{{ __('ui.landing.preview_contact') }}</strong>
                <span>{{ __('ui.landing.preview_contact_meta') }}</span>
            </div>
            <div class="metric">
                <strong>{{ __('ui.landing.preview_file_two') }}</strong>
                <span>{{ __('ui.landing.preview_file_two_meta') }}</span>
            </div>
            <div class="metric">
                <strong>{{ __('ui.storage.no_expiry') }}</strong>
                <span>{{ __('ui.landing.preview_title') }}</span>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <h2>{{ $landing['landing_features_title'] }}</h2>
                <p class="muted">{{ $landing['landing_features_body'] }}</p>
            </div>
            <div class="feature-grid">
                @foreach($features as $feature)
                    <article class="feature">
                        <div class="feature-media">
                            @if($feature['image_url'] !== '')
                                <img src="{{ $feature['image_url'] }}" alt="{{ $feature['title'] }}">
                            @else
                                <span>0{{ $feature['number'] }}</span>
                            @endif
                        </div>
                        <div class="feature-body">
                            <h3>{{ $feature['title'] }}</h3>
                            <p class="muted">{{ $feature['body'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section" style="padding-top: 0;">
            <div class="section-heading">
                <h2>{{ __('ui.landing.plans_title') }}</h2>
                <p class="muted">{{ __('ui.landing.plans_body') }}</p>
            </div>
            <div class="plans">
                @forelse($plans as $plan)
                    <article class="plan">
                        <div>
                            <h3>{{ $plan->name }}</h3>
                            @if($plan->description)
                                <p class="muted">{{ $plan->description }}</p>
                            @endif
                        </div>
                        <div class="plan-price">{{ \App\Support\PlanPolicy::formatPlanPrice($plan) }}</div>
                        <div class="muted">{{ \App\Support\PlanPolicy::formatPlanDuration($plan) }}</div>
                        @if($plan->isPaid())
                            <span class="badge launch-note">{{ __('ui.subscriptions.coming_soon') }}</span>
                        @else
                            <a class="button primary" href="{{ route('register') }}">{{ __('ui.landing.free_plan_cta') }}</a>
                        @endif
                    </article>
                @empty
                    <article class="plan">
                        <h3>{{ __('ui.landing.no_plans_title') }}</h3>
                        <p class="muted">{{ __('ui.landing.no_plans_body') }}</p>
                    </article>
                @endforelse
            </div>
        </section>
    </main>

    <footer>
        {{ __('ui.landing.footer', ['app' => $appName]) }}
    </footer>
</body>
</html>

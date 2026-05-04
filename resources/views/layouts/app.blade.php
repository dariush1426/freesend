<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    @php
        $layoutAppName = \App\Models\Setting::getValue('app_display_name', config('app.name', 'FreeSend'));
        $layoutPwaEnabled = \App\Models\Setting::getValue('pwa_enabled', 'true') === 'true';
        $layoutPwaThemeColor = \App\Models\Setting::getValue('pwa_theme_color', '#0f766e');
        $layoutPwaInstallPopupEnabled = \App\Models\Setting::getValue('pwa_install_popup_enabled', 'true') === 'true';
        $layoutQuickSendEnabled = \App\Models\Setting::getValue('quick_send_enabled', 'true') === 'true';
        $layoutLocale = app()->getLocale();
        $layoutIsAdminArea = ($layoutMode ?? null) === 'admin' || request()->routeIs('admin.*');
        $layoutPlanProfile = auth()->check()
            ? once(fn () => \App\Support\PlanPolicy::profileForUser(auth()->user()))
            : null;
        $layoutCurrentPlan = $layoutPlanProfile['plan'] ?? null;
        $layoutPersonalStorageEnabled = (bool) ($layoutPlanProfile['allow_personal_storage'] ?? false);
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $layoutAppName }}</title>
    @if($layoutPwaEnabled)
        <meta name="theme-color" content="{{ $layoutPwaThemeColor }}">
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <link rel="apple-touch-icon" href="{{ route('pwa.logo', 'mobile') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/fonts/vazirmatn/vazirmatn.css') }}?v=20260430">
    <style>
        :root {
            --bg: #eef2f7;
            --panel: #ffffff;
            --text: #18212f;
            --muted: #667085;
            --line: #d9e1ea;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --danger: #b42318;
            --soft: #eef6f5;
            --sidebar: #0f172a;
            --sidebar-soft: #1e293b;
            --sidebar-text: #dbe4f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            line-height: 1.7;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .app-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 96px minmax(0, 1fr);
        }
        .sidebar {
            background: linear-gradient(180deg, var(--sidebar) 0%, #111827 100%);
            color: var(--sidebar-text);
            padding: 18px 12px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 0 0 1px rgba(255,255,255,.03);
            z-index: 35;
            align-items: center;
        }
        .brand {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px 8px;
            border-radius: 16px;
            background: rgba(255,255,255,.04);
            align-items: center;
            text-align: center;
        }
        .brand strong { font-size: 18px; color: #fff; }
        .brand span, .muted { color: var(--muted); font-size: 13px; }
        .sidebar .brand span,
        .sidebar .muted { color: #94a3b8; }
        .sidebar-nav {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nav-label {
            font-size: 11px;
            color: #cbd5e1;
            line-height: 1.4;
        }
        .icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 20px;
        }
        .sidebar-link,
        .link-button,
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 9px 14px;
            background: var(--panel);
            color: var(--text);
            cursor: pointer;
            font-size: 14px;
            transition: .18s ease;
        }
        .sidebar-link {
            width: 100%;
            justify-content: center;
            gap: 8px;
            background: transparent;
            border-color: transparent;
            color: var(--sidebar-text);
            flex-direction: column;
            text-align: center;
            min-height: 64px;
            padding: 10px 8px;
        }
        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255,255,255,.08);
            border-color: rgba(255,255,255,.08);
            color: #fff;
        }
        .sidebar-footer {
            margin-top: auto;
        }
        .sidebar-footer form,
        .topbar-actions form { margin: 0; }
        .sidebar-footer .link-button {
            width: 100%;
            justify-content: center;
            background: var(--sidebar-soft);
            color: #fff;
            border-color: rgba(255,255,255,.08);
        }
        .content-shell {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            position: relative;
            z-index: 120;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(217,225,234,.8);
        }
        .topbar-title {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .topbar-title strong { font-size: 20px; }
        .topbar-subnav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .chip-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            border-radius: 999px;
            border: 1px solid var(--line);
            padding: 8px 12px;
            background: #fff;
            color: var(--text);
            font-size: 13px;
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .topbar-tools {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-menu {
            position: relative;
        }
        .topbar-menu[open] {
            z-index: 160;
        }
        .topbar-menu summary {
            list-style: none;
        }
        .topbar-menu summary::-webkit-details-marker {
            display: none;
        }
        .icon-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            cursor: pointer;
            transition: .18s ease;
        }
        .icon-button:hover,
        .topbar-menu[open] .icon-button {
            border-color: rgba(15,118,110,.24);
            box-shadow: 0 10px 24px rgba(15,23,42,.06);
        }
        .icon-svg {
            width: 20px;
            height: 20px;
            display: block;
        }
        .notification-count {
            position: absolute;
            top: -6px;
            inset-inline-end: -6px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            background: #d92d20;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }
        .dropdown-panel {
            position: absolute;
            top: calc(100% + 10px);
            inset-inline-end: 0;
            width: min(360px, calc(100vw - 28px));
            padding: 14px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid rgba(217,225,234,.95);
            box-shadow: 0 18px 40px rgba(15,23,42,.12);
            z-index: 170;
        }
        .dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .dropdown-header h3 {
            margin: 0;
            font-size: 15px;
        }
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .notification-link,
        .notification-empty {
            display: block;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid rgba(217,225,234,.95);
            background: #fbfdff;
        }
        .notification-link.unread {
            border-color: rgba(15,118,110,.2);
            background: linear-gradient(180deg, #ffffff 0%, #f4fbfa 100%);
        }
        .notification-link strong {
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .notification-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
        }
        .profile-shortcut {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 6px 10px 6px 6px;
            cursor: pointer;
        }
        .topbar-menu[open] .profile-shortcut,
        .profile-shortcut:hover {
            border-color: rgba(15,118,110,.24);
            box-shadow: 0 10px 24px rgba(15,23,42,.06);
        }
        .profile-shortcut .avatar {
            width: 32px;
            height: 32px;
            border-radius: 12px;
            flex-basis: 32px;
            font-size: 13px;
        }
        .profile-shortcut .label-stack {
            display: flex;
            flex-direction: column;
            gap: 1px;
            line-height: 1.3;
        }
        .profile-shortcut .label-stack strong {
            font-size: 13px;
            font-weight: 700;
        }
        .profile-shortcut .label-stack span {
            color: var(--muted);
            font-size: 12px;
        }
        .profile-menu .dropdown-panel {
            width: min(280px, calc(100vw - 28px));
            padding: 12px;
        }
        .profile-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 4px 12px;
            margin-bottom: 8px;
            border-bottom: 1px solid rgba(217,225,234,.95);
        }
        .profile-menu-header .avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            flex-basis: 42px;
        }
        .profile-menu-header .label-stack {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.35;
            min-width: 0;
        }
        .profile-menu-header .label-stack strong,
        .profile-menu-header .label-stack span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .profile-menu-actions {
            display: grid;
            gap: 6px;
        }
        .profile-menu-link,
        .profile-menu-actions form button {
            width: 100%;
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            border: none;
            background: transparent;
            color: var(--text);
            text-align: start;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
        }
        .profile-menu-link:hover,
        .profile-menu-actions form button:hover {
            background: rgba(15,118,110,.06);
        }
        .profile-menu-actions form {
            margin: 0;
        }
        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }
        .page {
            max-width: 1180px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .grid { display: grid; gap: 16px; }
        .grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .panel {
            background: var(--panel);
            border: 1px solid rgba(217,225,234,.95);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(15,23,42,.04);
        }
        h1, h2, h3 { margin: 0 0 14px; line-height: 1.4; }
        h1 { font-size: 26px; }
        h2 { font-size: 20px; }
        h3 { font-size: 16px; }
        form { margin: 0; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: rgba(15,118,110,.45);
            box-shadow: 0 0 0 4px rgba(15,118,110,.08);
        }
        textarea { min-height: 110px; resize: vertical; }
        .field { margin-bottom: 14px; }
        .status {
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--soft);
            color: var(--primary-dark);
            margin-bottom: 16px;
            border: 1px solid rgba(15,118,110,.12);
        }
        .errors {
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff1f0;
            color: var(--danger);
            margin-bottom: 16px;
            border: 1px solid rgba(180,35,24,.12);
        }
        .button.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .button.primary:hover { background: var(--primary-dark); }
        .link-button { font-family: inherit; }
        .list { display: grid; gap: 12px; }
        .item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .item:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15,23,42,.06);
            border-color: rgba(15,118,110,.18);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            background: var(--soft);
            color: var(--primary-dark);
            font-size: 12px;
            white-space: nowrap;
        }
        .message-stream {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 20px;
        }
        .message-row {
            display: flex;
        }
        .message-row.mine { justify-content: flex-start; }
        .message-row.theirs { justify-content: flex-end; }
        .message {
            width: min(100%, 720px);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 8px 20px rgba(15,23,42,.04);
        }
        .message.mine {
            background: #f0fdf9;
            border-color: rgba(15,118,110,.18);
        }
        .message.theirs {
            background: #fff;
        }
        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .messenger-layout {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 18px;
        }
        .messenger-layout.three-columns {
            grid-template-columns: 320px minmax(0, 1fr);
        }
        .thread-list-panel {
            padding: 18px;
        }
        .mobile-only {
            display: none;
        }
        .mobile-hidden {
            display: block;
        }
        .thread-search {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #fff 75%, rgba(255,255,255,0));
            padding-bottom: 12px;
            margin-bottom: 6px;
            z-index: 2;
        }
        .thread-list {
            display: grid;
            gap: 10px;
            max-height: calc(100vh - 240px);
            overflow: auto;
            padding-right: 2px;
        }
        .thread-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            transition: .15s ease;
            position: relative;
            overflow: visible;
        }
        .thread-card::before {
            content: '';
            position: absolute;
            inset-block: 10px;
            right: 0;
            width: 4px;
            border-radius: 999px;
            background: transparent;
            transition: .15s ease;
        }
        .thread-card:hover,
        .thread-card.active {
            border-color: rgba(15,118,110,.24);
            box-shadow: 0 10px 24px rgba(15,23,42,.06);
            background: #f9fffd;
        }
        .thread-card.active::before {
            background: var(--primary);
        }
        .thread-card.unread {
            border-color: rgba(15,118,110,.18);
            background: linear-gradient(180deg, #ffffff 0%, #f4fbfa 100%);
        }
        .thread-card.unread .thread-meta strong {
            color: var(--primary-dark);
        }
        .thread-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .thread-meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
            flex: 1;
        }
        .thread-meta strong,
        .thread-meta div {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 42px;
            background: linear-gradient(135deg, #dff7f2, #c7ecf1);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 15px;
            border: 1px solid rgba(15,118,110,.12);
        }
        .avatar.large {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            flex-basis: 52px;
            font-size: 18px;
        }
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            padding: 28px 18px;
            border: 1px dashed rgba(15,118,110,.2);
            border-radius: 18px;
            background: linear-gradient(180deg, #fcfffe 0%, #f7fbfa 100%);
        }
        .empty-state .empty-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(15,118,110,.08);
            color: var(--primary-dark);
            font-size: 24px;
        }
        .conversation-card {
            border: 1px solid rgba(15,118,110,.12);
            background: linear-gradient(180deg, #fcfffe 0%, #f7fbfa 100%);
        }
        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .soft-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 32px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(15,118,110,.08);
            color: var(--primary-dark);
            border: 1px solid rgba(15,118,110,.12);
            font-size: 13px;
            font-weight: 600;
        }
        .notification-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #ef4444;
            box-shadow: 0 0 0 4px rgba(239,68,68,.12);
            flex: 0 0 10px;
        }
        .meta-stack {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, .45);
            z-index: 210;
        }
        .conversation-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        @media (max-width: 980px) {
            .messenger-layout,
            .messenger-layout.three-columns,
            .grid.cols-3,
            .grid.cols-2 {
                grid-template-columns: 1fr;
            }
            .thread-list {
                max-height: none;
            }
        }
        @media (max-width: 860px) {
            .app-shell {
                grid-template-columns: 1fr;
                overflow-x: clip;
            }
            .sidebar {
                left: auto;
                position: fixed;
                right: 0;
                top: 0;
                width: min(82vw, 320px);
                max-width: 100vw;
                transform: translate3d(calc(100% + 24px), 0, 0);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: transform .22s ease, opacity .18s ease, visibility 0s linear .22s;
                align-items: stretch;
                padding: 20px 16px;
                z-index: 220;
            }
            .sidebar.open {
                transform: translate3d(0, 0, 0);
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transition: transform .22s ease, opacity .18s ease;
            }
            .mobile-overlay.show {
                display: block;
            }
            .menu-toggle {
                display: inline-flex;
                position: relative;
                z-index: 2;
            }
            .topbar {
                position: static;
                display: grid;
                grid-template-columns: auto minmax(0, 1fr);
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                backdrop-filter: none;
            }
            .topbar-title {
                display: none;
            }
            .topbar-title strong {
                font-size: 18px;
            }
            .topbar-actions {
                width: auto;
                flex: 0 0 auto;
                position: relative;
                z-index: 2;
            }
            .topbar-subnav {
                min-width: 0;
                justify-content: flex-end;
                flex-wrap: nowrap;
                gap: 8px;
            }
            .topbar-subnav > .chip-link {
                display: none;
            }
            .topbar-tools {
                margin-inline-start: 0;
                gap: 8px;
                flex-shrink: 0;
            }
            .topbar-tools .dropdown-panel {
                position: fixed;
                top: 86px;
                left: 14px;
                right: 14px;
                inset-inline-end: auto;
                width: auto;
                max-width: none;
                max-height: calc(100vh - 110px);
                overflow: auto;
            }
            .chip-link {
                width: 40px;
                min-width: 40px;
                min-height: 40px;
                padding: 0;
                border-radius: 14px;
                justify-content: center;
                flex: 0 0 40px;
            }
            .chip-link span:last-child {
                display: none;
            }
            .chip-link .icon {
                margin: 0;
            }
            .profile-shortcut {
                min-width: 40px;
                min-height: 40px;
                padding: 4px;
                justify-content: center;
            }
            .profile-shortcut .avatar {
                width: 30px;
                height: 30px;
                flex-basis: 30px;
            }
            .page {
                padding: 14px;
            }
            .item {
                align-items: flex-start;
                flex-direction: column;
            }
            .thread-card {
                align-items: flex-start;
            }
            .thread-main {
                width: 100%;
            }
            .message {
                width: 100%;
            }
            .mobile-only {
                display: inline-flex;
            }
            .mobile-hidden {
                display: none !important;
            }
            .sidebar-link {
                flex-direction: row;
                justify-content: flex-start;
                text-align: right;
                min-height: 48px;
                padding: 10px 12px;
            }
            .brand {
                align-items: flex-start;
                text-align: right;
                padding: 12px;
            }
            .chip-link {
                min-height: 36px;
                padding: 0;
            }
        }
        @media (max-width: 560px) {
            .topbar-actions {
                width: auto;
                justify-content: flex-start;
            }
            .topbar-subnav {
                width: auto;
                justify-content: flex-end;
            }
            .conversation-mobile-sidebar {
                display: none;
            }
            .button,
            .link-button,
            .sidebar-link {
                min-height: 44px;
            }
        }

        /* Phase 10 refresh */
        :root {
            --bg: #f2f6f2;
            --panel: rgba(255, 255, 255, 0.8);
            --panel-strong: #ffffff;
            --text: #11211c;
            --muted: #5d6d67;
            --line: rgba(17, 33, 28, 0.1);
            --line-strong: rgba(17, 33, 28, 0.16);
            --primary: #0f766e;
            --primary-dark: #115e59;
            --accent: #c57b18;
            --soft: rgba(15, 118, 110, 0.08);
            --sidebar: #12271f;
            --sidebar-soft: rgba(255, 255, 255, 0.08);
            --sidebar-text: #e5f2eb;
            --shadow-soft: 0 20px 45px rgba(15, 23, 42, 0.08);
            --shadow-panel: 0 30px 80px rgba(17, 33, 28, 0.12);
        }
        body {
            position: relative;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.14), transparent 26%),
                radial-gradient(circle at top right, rgba(197, 123, 24, 0.12), transparent 24%),
                linear-gradient(180deg, #f7faf7 0%, #eff5f1 48%, #edf2ef 100%);
            color: var(--text);
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            text-rendering: optimizeLegibility;
        }
        body::before,
        body::after {
            content: '';
            position: fixed;
            inset: auto;
            z-index: -1;
            pointer-events: none;
            border-radius: 999px;
            filter: blur(48px);
        }
        body::before {
            top: 88px;
            left: -110px;
            width: 280px;
            height: 280px;
            background: rgba(15, 118, 110, 0.12);
        }
        body::after {
            right: -90px;
            bottom: 80px;
            width: 240px;
            height: 240px;
            background: rgba(197, 123, 24, 0.12);
        }
        .app-shell {
            grid-template-columns: 282px minmax(0, 1fr);
            gap: 18px;
            padding: 18px;
        }
        .sidebar {
            height: calc(100vh - 36px);
            padding: 22px 16px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 80px rgba(2, 6, 23, 0.22);
            align-items: stretch;
            gap: 16px;
        }
        .brand {
            position: relative;
            overflow: hidden;
            padding: 18px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0.05));
            gap: 8px;
            align-items: flex-start;
            text-align: right;
            min-height: 108px;
        }
        .brand::before {
            content: '';
            position: absolute;
            left: -18px;
            top: -18px;
            width: 88px;
            height: 88px;
            border-radius: 28px;
            background: rgba(255,255,255,0.08);
            transform: rotate(12deg);
        }
        .brand strong {
            position: relative;
            z-index: 1;
            font-size: 24px;
            letter-spacing: -0.02em;
        }
        .brand-subtitle {
            position: relative;
            z-index: 1;
            max-width: 170px;
            line-height: 1.8;
            color: rgba(229, 242, 235, 0.78) !important;
            font-size: 12px !important;
        }
        .sidebar-nav {
            gap: 8px;
        }
        .sidebar-link {
            justify-content: space-between;
            flex-direction: row;
            text-align: right;
            min-height: 58px;
            padding: 12px 14px;
            border-radius: 18px;
            background: transparent;
            backdrop-filter: blur(10px);
        }
        .sidebar-link .icon {
            order: 2;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 14px;
            background: rgba(255,255,255,0.07);
            color: #ffffff;
        }
        .nav-label {
            flex: 1;
            font-size: 14px;
            font-weight: 700;
            color: inherit;
        }
        .sidebar-link:hover,
        .sidebar-link.active {
            transform: translateX(-3px);
            background: linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0.08));
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06), 0 14px 28px rgba(2, 6, 23, 0.16);
        }
        .sidebar-link .badge {
            background: rgba(255,255,255,0.14);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.12);
        }
        .sidebar-footer {
            padding: 14px;
            border-radius: 22px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-footer .link-button {
            background: rgba(255,255,255,0.08);
        }
        .content-shell {
            gap: 16px;
        }
        .topbar {
            margin-top: 0;
            padding: 18px 22px;
            border: 1px solid rgba(255,255,255,0.78);
            border-radius: 28px;
            background: rgba(255,255,255,0.74);
            backdrop-filter: blur(18px);
            box-shadow: var(--shadow-soft);
        }
        .topbar-title strong {
            font-size: 24px;
            letter-spacing: -0.03em;
        }
        .topbar-subnav {
            gap: 10px;
        }
        .chip-link {
            min-height: 40px;
            padding: 9px 14px;
            border: 1px solid rgba(17, 33, 28, 0.08);
            background: rgba(255,255,255,0.78);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.04);
            transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
        }
        .chip-link:hover {
            transform: translateY(-2px);
            border-color: rgba(15, 118, 110, 0.18);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }
        .menu-toggle {
            border-radius: 14px;
            border-color: rgba(17, 33, 28, 0.1);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }
        .page {
            max-width: 1280px;
            padding: 0 4px 26px;
        }
        .panel {
            border-radius: 28px;
            padding: 24px;
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.76);
            box-shadow: var(--shadow-soft);
        }
        h1,
        h2,
        h3 {
            letter-spacing: -0.03em;
        }
        h1 {
            font-size: 34px;
            margin-bottom: 12px;
        }
        h2 {
            font-size: 23px;
            margin-bottom: 12px;
        }
        h3 {
            font-size: 17px;
            margin-bottom: 10px;
        }
        label {
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
        }
        input,
        textarea,
        select {
            border-radius: 16px;
            padding: 13px 15px;
            background: rgba(247, 250, 248, 0.94);
            border: 1px solid rgba(17, 33, 28, 0.1);
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }
        input::placeholder,
        textarea::placeholder {
            color: rgba(93, 109, 103, 0.88);
        }
        input:focus,
        textarea:focus,
        select:focus {
            border-color: rgba(15, 118, 110, 0.4);
            background: #ffffff;
            box-shadow: 0 0 0 5px rgba(15, 118, 110, 0.08);
        }
        select {
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, rgba(17, 33, 28, 0.45) 50%),
                linear-gradient(135deg, rgba(17, 33, 28, 0.45) 50%, transparent 50%);
            background-position:
                calc(16px) calc(50% - 3px),
                calc(10px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-left: 34px;
        }
        .field {
            margin-bottom: 16px;
        }
        .button,
        .link-button {
            min-height: 46px;
            padding: 10px 16px;
            border-radius: 16px;
            border-color: rgba(17, 33, 28, 0.1);
            background: rgba(255,255,255,0.9);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
            font-weight: 600;
        }
        .button:hover,
        .link-button:hover {
            transform: translateY(-1px);
            border-color: rgba(15, 118, 110, 0.16);
        }
        .button.primary {
            background: linear-gradient(135deg, #0f766e 0%, #149e90 100%);
            border-color: transparent;
            box-shadow: 0 16px 28px rgba(15, 118, 110, 0.22);
        }
        .button.primary:hover {
            background: linear-gradient(135deg, #115e59 0%, #0f766e 100%);
        }
        .status,
        .errors {
            border-radius: 20px;
            padding: 14px 16px;
            border-width: 1px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }
        .status {
            background: linear-gradient(180deg, rgba(15,118,110,0.1), rgba(15,118,110,0.05));
        }
        .errors {
            background: linear-gradient(180deg, rgba(180,35,24,0.1), rgba(180,35,24,0.05));
        }
        .list {
            gap: 14px;
        }
        .item {
            padding: 16px 18px;
            border-radius: 20px;
            border: 1px solid rgba(17, 33, 28, 0.08);
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(246,249,247,0.92));
        }
        .item:hover {
            transform: translateY(-4px);
            border-color: rgba(15, 118, 110, 0.16);
            box-shadow: 0 18px 32px rgba(15, 23, 42, 0.08);
        }
        .badge {
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(15, 118, 110, 0.1);
            background: rgba(15, 118, 110, 0.08);
        }
        .page-hero {
            position: relative;
            overflow: visible;
            padding: 28px;
            border-radius: 32px;
            background: linear-gradient(135deg, rgba(15,118,110,0.97) 0%, rgba(20,158,144,0.88) 100%);
            color: #ffffff;
            box-shadow: var(--shadow-panel);
            isolation: isolate;
            z-index: 30;
        }
        .page-hero::before,
        .page-hero::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
        }
        .page-hero::before {
            top: -46px;
            left: -16px;
            width: 160px;
            height: 160px;
        }
        .page-hero::after {
            right: -56px;
            bottom: -72px;
            width: 220px;
            height: 220px;
        }
        .page-hero > * {
            position: relative;
            z-index: 1;
        }
        .page-hero h1,
        .page-hero h2,
        .page-hero h3,
        .page-hero strong,
        .page-hero .badge {
            color: #ffffff;
        }
        .page-hero .muted {
            color: rgba(240, 253, 250, 0.82);
        }
        .page-hero .button {
            background: rgba(255,255,255,0.15);
            color: #ffffff;
            border-color: rgba(255,255,255,0.18);
            box-shadow: none;
        }
        .page-hero .button.primary {
            background: #ffffff;
            color: var(--primary-dark);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            margin-bottom: 14px;
            background: rgba(255,255,255,0.14);
            color: inherit;
            font-size: 12px;
            font-weight: 700;
        }
        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .title-with-help,
        .label-with-help {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .title-with-help.has-open-help,
        .label-with-help.has-open-help {
            z-index: 90;
        }
        .label-with-help {
            margin-bottom: 8px;
        }
        .label-with-help label {
            margin: 0;
        }
        .inline-help {
            position: relative;
            display: inline-flex;
            flex-shrink: 0;
            z-index: 40;
        }
        .inline-help summary {
            list-style: none;
        }
        .inline-help summary::-webkit-details-marker {
            display: none;
        }
        .inline-help-toggle {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid rgba(17, 33, 28, 0.12);
            background: rgba(255,255,255,0.9);
            color: var(--primary-dark);
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            line-height: 1;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
        }
        .inline-help[open] .inline-help-toggle,
        .inline-help-toggle:hover {
            transform: translateY(-1px);
            border-color: rgba(15, 118, 110, 0.22);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }
        .inline-help-panel {
            position: absolute;
            top: calc(100% + 10px);
            inset-inline-start: 0;
            width: max-content;
            min-width: min(240px, calc(100vw - 32px));
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.98);
            color: var(--text);
            border: 1px solid rgba(17, 33, 28, 0.1);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
            font-size: 13px;
            line-height: 1.8;
            white-space: normal;
            z-index: 220;
        }
        .inline-help.align-end .inline-help-panel {
            inset-inline-start: auto;
            inset-inline-end: 0;
        }
        .page-hero .inline-help-toggle {
            border-color: rgba(255,255,255,0.24);
            background: rgba(255,255,255,0.14);
            color: #ffffff;
            box-shadow: none;
        }
        .page-hero .inline-help[open] .inline-help-toggle,
        .page-hero .inline-help-toggle:hover {
            border-color: rgba(255,255,255,0.38);
            background: rgba(255,255,255,0.22);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.16);
        }
        .page-hero .inline-help-panel {
            background: rgba(255,255,255,0.98);
            color: var(--text);
            z-index: 1000;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .stat-card {
            position: relative;
            overflow: visible;
            padding: 22px;
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(246,249,247,0.88));
            border: 1px solid rgba(255,255,255,0.72);
            box-shadow: var(--shadow-soft);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 14px;
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: rgba(15, 118, 110, 0.08);
        }
        .stat-card > * {
            position: relative;
            z-index: 1;
        }
        .stat-card .value {
            display: block;
            margin-top: 18px;
            font-size: 44px;
            line-height: 1;
            letter-spacing: -0.05em;
        }
        .card-kicker {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }
        .helper-text {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }
        .wizard-sidebar {
            position: sticky;
            top: 12px;
            align-self: start;
        }
        .wizard-steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 18px 0 24px;
        }
        .wizard-step {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.88), rgba(246,249,247,0.92));
            border: 1px solid rgba(17, 33, 28, 0.08);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
        }
        .wizard-step-index {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            border-radius: 12px;
            background: rgba(15, 118, 110, 0.1);
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
        }
        .section-block {
            padding: 22px;
            border-radius: 24px;
            border: 1px solid rgba(17, 33, 28, 0.08);
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(247,250,248,0.94));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
        }
        .section-block + .section-block {
            margin-top: 16px;
        }
        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .section-heading h2,
        .section-heading h3,
        .section-heading p {
            margin: 0;
        }
        .section-heading .title-with-help h2,
        .section-heading .title-with-help h3 {
            margin: 0;
        }
        .file-dropzone {
            padding: 18px;
            border-radius: 22px;
            border: 1.5px dashed rgba(15, 118, 110, 0.2);
            background: radial-gradient(circle at top, rgba(15,118,110,0.07), transparent 62%), rgba(248, 251, 249, 0.9);
            transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .file-dropzone.dragover {
            border-color: rgba(15, 118, 110, 0.42);
            background: radial-gradient(circle at top, rgba(15,118,110,0.12), transparent 62%), rgba(240, 252, 248, 0.96);
            box-shadow: 0 18px 32px rgba(15, 118, 110, 0.08);
            transform: translateY(-1px);
        }
        .file-dropzone.has-file {
            border-style: solid;
            border-color: rgba(15, 118, 110, 0.18);
            background: radial-gradient(circle at top, rgba(15,118,110,0.08), transparent 58%), rgba(249, 252, 250, 0.96);
        }
        .dropzone-copy {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        .dropzone-copy strong {
            font-size: 16px;
        }
        .dropzone-copy .row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dropzone-note {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px dashed rgba(15,118,110,0.18);
            background: rgba(255,255,255,0.66);
            color: var(--muted);
            font-size: 13px;
        }
        .wizard-stage[hidden] {
            display: none !important;
        }
        .wizard-locked-recipient {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(15,118,110,0.12);
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(241,250,247,0.94));
            margin-bottom: 14px;
        }
        .wizard-locked-recipient .badge {
            margin-bottom: 10px;
        }
        .file-dropzone input[type="file"] {
            background: transparent;
            border: none;
            padding: 0;
        }
        .file-dropzone input[type="file"]::file-selector-button {
            margin-left: 12px;
            min-height: 42px;
            padding: 10px 16px;
            border-radius: 14px;
            border: 1px solid rgba(17, 33, 28, 0.1);
            background: rgba(255,255,255,0.96);
            color: var(--text);
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .checkbox-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(15,118,110,0.06);
            border: 1px solid rgba(15,118,110,0.1);
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        .thread-list-panel {
            background: rgba(255,255,255,0.74);
        }
        .thread-search {
            background: linear-gradient(180deg, rgba(248,250,249,0.98) 76%, rgba(248,250,249,0));
        }
        .thread-card {
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(247,250,248,0.92));
            border: 1px solid rgba(17, 33, 28, 0.08);
        }
        .thread-card::before {
            right: 10px;
            inset-block: 12px;
        }
        .thread-card:hover,
        .thread-card.active {
            transform: translateY(-3px);
            box-shadow: 0 20px 34px rgba(15, 23, 42, 0.08);
        }
        .thread-card.unread {
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(238,249,246,0.92));
        }
        .message-stream {
            gap: 18px;
            margin-top: 22px;
        }
        .message {
            border-radius: 24px;
            padding: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247,250,248,0.95));
        }
        .message.mine {
            background: linear-gradient(180deg, rgba(239,253,248,0.98), rgba(229,247,242,0.96));
        }
        .message h3 {
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .empty-state {
            padding: 32px 20px;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(246,249,247,0.9));
        }
        .conversation-card {
            border-radius: 22px;
        }
        .page-hero.has-open-help,
        .panel.has-open-help,
        .section-block.has-open-help,
        .stat-card.has-open-help,
        .thread-search.has-open-help {
            position: relative;
            z-index: 80;
        }
        .action-bar {
            gap: 10px;
        }
        code {
            padding: 2px 7px;
            border-radius: 10px;
            background: rgba(17, 33, 28, 0.06);
            color: var(--text);
        }
        #receiver-suggestions {
            padding: 6px;
            background: rgba(255,255,255,0.96) !important;
            border: 1px solid rgba(17, 33, 28, 0.1) !important;
            border-radius: 18px !important;
            box-shadow: 0 20px 36px rgba(15, 23, 42, 0.12) !important;
        }
        #receiver-suggestions button {
            border-radius: 14px;
        }
        #receiver-suggestions button:hover {
            background: rgba(15, 118, 110, 0.06) !important;
        }
        .locale-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .category-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(17, 33, 28, 0.08);
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            transition: .16s ease;
        }
        .category-pill.active {
            background: rgba(15,118,110,0.1);
            border-color: rgba(15,118,110,0.16);
            color: var(--primary-dark);
        }
        .icon-badge {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            border-radius: 12px;
            background: rgba(15,118,110,0.08);
            color: var(--primary-dark);
            font-size: 16px;
            font-weight: 800;
            border: 1px solid rgba(15,118,110,0.1);
        }
        .icon-badge.archive {
            background: rgba(197,123,24,0.1);
            color: #8a5713;
            border-color: rgba(197,123,24,0.12);
        }
        .icon-badge.video {
            background: rgba(59,130,246,0.1);
            color: #1d4ed8;
            border-color: rgba(59,130,246,0.12);
        }
        .icon-badge.document {
            background: rgba(99,102,241,0.1);
            color: #4338ca;
            border-color: rgba(99,102,241,0.12);
        }
        .file-preview-card {
            display: inline-flex;
            flex-direction: column;
            gap: 8px;
        }
        .file-preview-frame {
            width: 120px;
            height: 120px;
            border-radius: 22px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(243,247,245,0.96));
            border: 1px solid rgba(17, 33, 28, 0.08);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        }
        .file-preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .file-preview-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(15,118,110,0.12), transparent 58%),
                linear-gradient(180deg, rgba(255,255,255,0.98), rgba(243,247,245,0.96));
        }
        .file-preview-glyph {
            font-size: 30px;
            line-height: 1;
        }
        .file-preview-ext {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
        }
        .file-preview-caption {
            display: flex;
            justify-content: center;
        }
        .history-card,
        .exchange-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }
        .history-card-main,
        .exchange-card-main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .mini-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .icon-button {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            border: 1px solid rgba(17, 33, 28, 0.08);
            background: rgba(255,255,255,0.88);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
            color: var(--text);
            cursor: pointer;
            font-size: 18px;
            transition: .16s ease;
        }
        .icon-button:hover {
            transform: translateY(-1px);
            border-color: rgba(15,118,110,0.16);
        }
        .message-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }
        .message-tools {
            position: relative;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }
        .message-menu {
            position: absolute;
            top: calc(100% + 8px);
            inset-inline-end: 0;
            min-width: 220px;
            padding: 8px;
            border-radius: 18px;
            background: rgba(255,255,255,0.98);
            border: 1px solid rgba(17,33,28,0.08);
            box-shadow: 0 18px 36px rgba(15,23,42,0.14);
            display: none;
            z-index: 25;
        }
        .message-menu.open {
            display: grid;
            gap: 6px;
        }
        .message-menu-item,
        .message-menu form button {
            width: 100%;
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            border: none;
            background: transparent;
            color: var(--text);
            text-align: start;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
        }
        .message-menu-item:hover,
        .message-menu form button:hover {
            background: rgba(15,118,110,0.06);
        }
        .message-menu form {
            margin: 0;
        }
        .message-statuses {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .detail-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2,6,23,0.52);
            z-index: 70;
        }
        .detail-modal.open {
            display: flex;
        }
        .detail-modal-card {
            width: min(100%, 480px);
            max-height: calc(100vh - 40px);
            overflow: auto;
            border-radius: 28px;
            background: rgba(255,255,255,0.98);
            border: 1px solid rgba(255,255,255,0.78);
            box-shadow: 0 26px 60px rgba(15,23,42,0.22);
            padding: 24px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
            margin-top: 18px;
        }
        .detail-grid strong {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
        }
        .detail-grid span {
            color: var(--muted);
            font-size: 14px;
        }
        .history-results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        @media (max-width: 1080px) {
            .stats-grid,
            .wizard-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 980px) {
            .wizard-sidebar {
                position: static;
            }
        }
        @media (max-width: 860px) {
            .app-shell {
                padding: 0;
                gap: 0;
            }
            .sidebar {
                height: 100vh;
                border-radius: 0;
                width: min(86vw, 340px);
            }
            .topbar,
            .page {
                border-radius: 0;
                padding-left: 14px;
                padding-right: 14px;
            }
            .topbar {
                margin-bottom: 0;
            }
            .page {
                padding-top: 14px;
                padding-bottom: 18px;
            }
            .page-hero,
            .panel,
            .stat-card,
            .section-block {
                border-radius: 24px;
            }
            .brand {
                min-height: auto;
            }
        }
        @media (max-width: 640px) {
            h1 {
                font-size: 28px;
            }
            h2 {
                font-size: 21px;
            }
            .stats-grid,
            .wizard-steps {
                grid-template-columns: 1fr;
            }
            .history-card,
            .exchange-card {
                grid-template-columns: 1fr;
            }
            .file-preview-frame {
                width: 100%;
                height: 180px;
            }
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .page-hero {
                padding: 22px;
            }
            .profile-shortcut .label-stack {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside id="sidebar" class="sidebar">
        <a class="brand" href="{{ auth()->check() ? ($layoutIsAdminArea ? route('admin.dashboard') : route('dashboard')) : route('login') }}">
            <strong>{{ $layoutAppName }}</strong>
            <span class="brand-subtitle">{{ $layoutIsAdminArea ? __('admin.panel_title') : __('ui.layout.brand_subtitle') }}</span>
        </a>

        <nav class="sidebar-nav">
            @auth
                @if($layoutIsAdminArea)
                    <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <span class="icon">&#128202;</span>
                        <span class="nav-label">{{ __('admin.nav.dashboard') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}">
                        <span class="icon">&#129513;</span>
                        <span class="nav-label">{{ __('admin.nav.plans') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('admin.subscribers.*') ? 'active' : '' }}" href="{{ route('admin.subscribers.index') }}">
                        <span class="icon">&#128101;</span>
                        <span class="nav-label">{{ __('admin.nav.subscribers') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">
                        <span class="icon">&#128179;</span>
                        <span class="nav-label">{{ __('admin.nav.orders') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                        <span class="icon">&#9881;</span>
                        <span class="nav-label">{{ __('admin.nav.settings') }}</span>
                    </a>
                    <a class="sidebar-link" href="{{ route('dashboard') }}">
                        <span class="icon">&#8617;</span>
                        <span class="nav-label">{{ __('admin.nav.back_to_app') }}</span>
                    </a>
                @else
                    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <span class="icon">&#127968;</span>
                        <span class="nav-label">{{ __('ui.layout.dashboard') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('files.*') ? 'active' : '' }}" href="{{ route('files.create') }}">
                        <span class="icon">&#128228;</span>
                        <span class="nav-label">{{ __('ui.layout.send') }}</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('inbox') || request()->routeIs('conversations.*') ? 'active' : '' }}" href="{{ route('inbox') }}">
                        <span class="icon">&#128172;</span>
                        <span class="nav-label">{{ __('ui.layout.inbox') }}</span>
                        @if($layoutUnreadNotifications > 0)
                            <span class="badge">{{ $layoutUnreadNotifications }}</span>
                        @endif
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('history.*') ? 'active' : '' }}" href="{{ route('history.index') }}">
                        <span class="icon">&#128339;</span>
                        <span class="nav-label">{{ __('ui.layout.history') }}</span>
                    </a>
                    @if($layoutPersonalStorageEnabled)
                        <a class="sidebar-link {{ request()->routeIs('storage.*') ? 'active' : '' }}" href="{{ route('storage.index') }}">
                            <span class="icon">&#128451;</span>
                            <span class="nav-label">{{ __('ui.layout.storage') }}</span>
                        </a>
                    @endif
                @endif
            @else
                <a class="sidebar-link" href="{{ route('login') }}">
                    <span class="icon">&#128274;</span>
                    <span class="nav-label">{{ __('ui.layout.login') }}</span>
                </a>
                <a class="sidebar-link" href="{{ route('register') }}">
                    <span class="icon">&#128221;</span>
                    <span class="nav-label">{{ __('ui.layout.register') }}</span>
                </a>
                @if($layoutQuickSendEnabled)
                    <a class="sidebar-link" href="{{ route('quick-send.create') }}">
                        <span class="icon">Q</span>
                        <span class="nav-label">{{ __('ui.quick_send.title') }}</span>
                    </a>
                @endif
            @endauth
        </nav>

    </aside>

    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-actions">
                <button id="menu-toggle" class="menu-toggle" type="button" aria-label="{{ __('ui.layout.open_menu') }}">&#9776;</button>
                <div class="topbar-title">
                    <strong>@yield('page_title', 'FreeSend')</strong>
                </div>
            </div>
            @auth
                <div class="topbar-subnav">
                    @if($layoutIsAdminArea)
                        <a class="chip-link" href="{{ route('admin.dashboard') }}"><span class="icon">&#128202;</span><span>{{ __('admin.nav.dashboard') }}</span></a>
                        <a class="chip-link" href="{{ route('admin.plans.index') }}"><span class="icon">&#129513;</span><span>{{ __('admin.nav.plans') }}</span></a>
                        <a class="chip-link" href="{{ route('admin.subscribers.index') }}"><span class="icon">&#128101;</span><span>{{ __('admin.nav.subscribers') }}</span></a>
                        <a class="chip-link" href="{{ route('admin.orders.index') }}"><span class="icon">&#128179;</span><span>{{ __('admin.nav.orders') }}</span></a>
                        <a class="chip-link" href="{{ route('admin.settings.edit') }}"><span class="icon">&#9881;</span><span>{{ __('admin.nav.settings') }}</span></a>
                        <a class="chip-link" href="{{ route('dashboard') }}"><span class="icon">&#8617;</span><span>{{ __('admin.nav.back_to_app') }}</span></a>
                    @else
                        <a class="chip-link" href="{{ route('files.create') }}"><span class="icon">&#128228;</span><span>{{ __('ui.layout.send') }}</span></a>
                        <a class="chip-link" href="{{ route('inbox') }}"><span class="icon">&#128101;</span><span>{{ __('ui.layout.recent_contacts') }}</span></a>
                        <a class="chip-link" href="{{ route('history.index') }}"><span class="icon">&#128339;</span><span>{{ __('ui.layout.history') }}</span></a>
                        @if($layoutPersonalStorageEnabled)
                            <a class="chip-link" href="{{ route('storage.index') }}"><span class="icon">&#128451;</span><span>{{ __('ui.layout.storage') }}</span></a>
                        @endif
                    @endif
                    <div class="topbar-tools">
                        @unless($layoutIsAdminArea)
                        <details class="topbar-menu">
                            <summary class="icon-button" aria-label="{{ __('ui.notifications.title') }}">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M15 18H9M17 9A5 5 0 1 0 7 9C7 12.314 5.5 13.667 4 15H20C18.5 13.667 17 12.314 17 9Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                @if($layoutUnreadNotifications > 0)
                                    <span class="notification-count">{{ $layoutUnreadNotifications > 99 ? '99+' : $layoutUnreadNotifications }}</span>
                                @endif
                            </summary>
                            <div class="dropdown-panel">
                                <div class="dropdown-header">
                                    <h3>{{ __('ui.notifications.title') }}</h3>
                                    @if($layoutUnreadNotifications > 0)
                                        <form method="post" action="{{ route('notifications.read-all') }}">
                                            @csrf
                                            <button class="button" type="submit">{{ __('ui.notifications.read_all') }}</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="notification-list">
                                    @forelse($layoutRecentNotifications as $notification)
                                        <a class="notification-link {{ $notification->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $notification) }}">
                                            <strong>{{ $notification->title }}</strong>
                                            <div class="muted">{{ $notification->body }}</div>
                                            <div class="notification-meta">
                                                <span>{{ $notification->created_at?->diffForHumans() }}</span>
                                                @if(!$notification->read_at)
                                                    <span class="soft-badge">{{ __('ui.statuses.unread') }}</span>
                                                @endif
                                            </div>
                                        </a>
                                    @empty
                                        <div class="notification-empty">
                                            <strong>{{ __('ui.notifications.empty_title') }}</strong>
                                            <div class="muted">{{ __('ui.notifications.empty_body') }}</div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </details>
                        @endunless
                        <details class="topbar-menu profile-menu">
                            <summary class="profile-shortcut" aria-label="{{ __('ui.layout.profile') }}">
                                <span class="avatar">{{ mb_substr(auth()->user()->full_name ?: auth()->user()->username, 0, 1) }}</span>
                                <span class="label-stack">
                                    <strong>{{ __('ui.layout.profile') }}</strong>
                                    <span>
                                        {{ auth()->user()->username }}
                                        @if($layoutCurrentPlan)
                                            &bull; {{ $layoutCurrentPlan->name }}
                                        @endif
                                    </span>
                                </span>
                            </summary>
                            <div class="dropdown-panel">
                                <div class="profile-menu-header">
                                    <span class="avatar">{{ mb_substr(auth()->user()->full_name ?: auth()->user()->username, 0, 1) }}</span>
                                    <span class="label-stack">
                                        <strong>{{ auth()->user()->full_name ?: __('ui.layout.profile') }}</strong>
                                        <span>{{ auth()->user()->username }}</span>
                                    </span>
                                </div>
                                @if($layoutCurrentPlan)
                                    <div class="status" style="margin-bottom: 12px;">
                                        {{ __('ui.layout.current_plan') }}: {{ $layoutCurrentPlan->name }}
                                    </div>
                                @endif
                                <div class="profile-menu-actions">
                                    @if(auth()->user()->is_admin)
                                        <a class="profile-menu-link" href="{{ $layoutIsAdminArea ? route('dashboard') : route('admin.dashboard') }}">
                                            {{ $layoutIsAdminArea ? __('ui.layout.back_to_user_panel') : __('ui.layout.admin_panel') }}
                                        </a>
                                    @endif
                                    @if($layoutPersonalStorageEnabled)
                                        <a class="profile-menu-link" href="{{ route('storage.index') }}">{{ __('ui.layout.storage') }}</a>
                                    @endif
                                    <a class="profile-menu-link" href="{{ route('subscriptions.upgrade') }}">{{ __('ui.layout.upgrade_subscription') }}</a>
                                    <a class="profile-menu-link" href="{{ route('profile.edit') }}">{{ __('ui.layout.account_settings') }}</a>
                                    <form method="post" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit">{{ __('ui.layout.logout') }}</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            @endauth
        </header>

        <main class="page">
            @if(session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<div id="mobile-overlay" class="mobile-overlay"></div>
@if($layoutPwaEnabled && $layoutPwaInstallPopupEnabled)
    <div id="pwa-install-modal" class="mobile-overlay" style="z-index:60;">
        <div class="panel" style="max-width:420px;margin:12vh auto 0;">
            <h3 style="margin-bottom:8px;">{{ __('ui.layout.install_title', ['app' => $layoutAppName]) }}</h3>
            <p class="muted" style="margin-top:0;">{{ __('ui.layout.install_body') }}</p>
            <div class="actions" style="margin-top:12px;">
                <button id="pwa-install-confirm" class="button primary" type="button">{{ __('ui.layout.install_confirm') }}</button>
                <button id="pwa-install-later" class="button" type="button">{{ __('ui.layout.later') }}</button>
            </div>
            <p id="pwa-ios-hint" class="muted" style="display:none;margin-top:10px;">
                {{ __('ui.layout.ios_hint') }}
            </p>
            <p id="pwa-desktop-hint" class="muted" style="display:none;margin-top:10px;">
                {{ __('ui.layout.desktop_hint') }}
            </p>
        </div>
    </div>
@endif

<script>
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menu-toggle');
    const mobileOverlay = document.getElementById('mobile-overlay');

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        mobileOverlay?.classList.remove('show');
    };

    const toggleSidebar = () => {
        sidebar?.classList.toggle('open');
        mobileOverlay?.classList.toggle('show');
    };

    menuToggle?.addEventListener('click', toggleSidebar);
    mobileOverlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 860) {
            closeSidebar();
        }
    });

    (() => {
        const inlineHelpItems = Array.from(document.querySelectorAll('.inline-help'));

        if (inlineHelpItems.length === 0) {
            return;
        }

        const syncInlineHelpLayers = () => {
            document.querySelectorAll('.has-open-help').forEach((element) => {
                element.classList.remove('has-open-help');
            });

            inlineHelpItems.forEach((item) => {
                if (!item.open) {
                    return;
                }

                item.closest('.title-with-help, .label-with-help')?.classList.add('has-open-help');
                item.closest('.page-hero, .panel, .section-block, .stat-card, .thread-search')?.classList.add('has-open-help');
            });
        };

        const closeInlineHelp = (current = null) => {
            inlineHelpItems.forEach((item) => {
                if (item !== current) {
                    item.removeAttribute('open');
                }
            });
            syncInlineHelpLayers();
        };

        inlineHelpItems.forEach((item) => {
            item.addEventListener('toggle', () => {
                if (item.open) {
                    closeInlineHelp(item);
                }
                syncInlineHelpLayers();
            });
        });

        document.addEventListener('click', (event) => {
            const target = event.target;

            if (!(target instanceof Element) || !target.closest('.inline-help')) {
                closeInlineHelp();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeInlineHelp();
            }
        });

        syncInlineHelpLayers();
    })();

    @if($layoutPwaEnabled)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('{{ route('pwa.sw') }}')
                .then((registration) => registration.update())
                .catch(() => {});
        });
    }
    @endif

    @if($layoutPwaEnabled && $layoutPwaInstallPopupEnabled)
    (() => {
        const modal = document.getElementById('pwa-install-modal');
        const installBtn = document.getElementById('pwa-install-confirm');
        const laterBtn = document.getElementById('pwa-install-later');
        const iosHint = document.getElementById('pwa-ios-hint');
        const desktopHint = document.getElementById('pwa-desktop-hint');
        const seenKey = 'pwa_install_prompt_seen_v2';
        const installConfirmLabel = @json(__('ui.layout.install_confirm'));
        const installGuideLabel = @json(__('ui.layout.install_guide'));
        let deferredPrompt = null;

        if (!modal || localStorage.getItem(seenKey) === '1') {
            return;
        }

        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isStandalone) {
            localStorage.setItem(seenKey, '1');
            return;
        }

        const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        const isChromiumDesktop = !isIos && /chrome|edg/i.test(window.navigator.userAgent);

        const openModal = () => {
            modal.classList.add('show');
        };

        const closeModal = (persist = false) => {
            modal.classList.remove('show');
            if (persist) {
                localStorage.setItem(seenKey, '1');
            }
        };

        laterBtn?.addEventListener('click', () => closeModal(true));

        if (isIos) {
            iosHint.style.display = 'block';
            installBtn.textContent = installGuideLabel;
            installBtn.addEventListener('click', () => {
                iosHint.style.display = 'block';
            });
            openModal();
            return;
        }

        if (isChromiumDesktop) {
            desktopHint.style.display = 'block';
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            installBtn.disabled = false;
            installBtn.textContent = installConfirmLabel;
            openModal();
        });

        // Show our custom popup on first run even if browser prompt isn't ready yet.
        setTimeout(() => {
            if (!modal.classList.contains('show')) {
                if (!deferredPrompt) {
                    installBtn.textContent = installGuideLabel;
                }
                openModal();
            }
        }, 1200);

        installBtn?.addEventListener('click', async () => {
            if (!deferredPrompt) {
                desktopHint.style.display = 'block';
                return;
            }

            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } catch (error) {
                // ignore
            }
            deferredPrompt = null;
            closeModal(true);
        });

        window.addEventListener('appinstalled', () => {
            closeModal(true);
        });
    })();
    @endif
</script>
</body>
</html>

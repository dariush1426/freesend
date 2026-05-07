@extends('layouts.app')

@section('page_title', __('ui.storage.title'))

@section('content')
    @php
        $thumbnailDimensions = [
            'sm' => 92,
            'md' => 132,
            'lg' => 184,
            'xl' => 240,
        ];
        $thumbPixels = $thumbnailDimensions[$thumbnailSize] ?? 132;
        $contactLabel = __('ui.storage.contact_label');
        $contactPlaceholder = __('ui.storage.contact_placeholder');
        $contactActiveLabel = __('ui.storage.contact_filter_active', ['user' => $filters['contact'] ?? '']);
        $clearContactLabel = __('ui.storage.contact_filter_clear');
        $folderTreeTitle = __('ui.storage.folder_tree_title');
        $folderTreeBody = __('ui.storage.folder_tree_body');
        $selectedTypes = collect(explode(',', (string) ($filters['type'] ?? 'all')))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $selectedTypes = in_array('all', $selectedTypes, true) || $selectedTypes === [] ? ['all'] : $selectedTypes;
        $availableContacts = collect($availableSenders)
            ->merge($availableRecipients)
            ->sortKeys()
            ->all();
        $contactValue = (string) ($filters['contact'] ?? '');
        $contactLabelValue = $contactValue !== '' ? ($availableContacts[$contactValue] ?? $contactValue) : '';
        $queryBase = [
            'q' => $filters['search'] ?? '',
            'type' => $filters['type'] ?? 'all',
            'contact' => $filters['contact'] ?? '',
            'scope' => $filters['scope'] ?? 'all',
            'period' => $filters['period'] ?? 'all',
            'view' => $viewMode,
            'thumb' => $thumbnailSize,
        ];
        $typeSummary = in_array('all', $selectedTypes, true)
            ? __('ui.file_types.all')
            : collect($selectedTypes)->map(fn ($type) => $type === 'note' ? __('ui.storage.note_badge') : __('ui.file_types.'.$type))->implode('، ');
        $scopeSummary = __('ui.storage.scope_'.($filters['scope'] ?? 'all'));
        $periodSummary = __('ui.storage.period_'.($filters['period'] ?? 'all'));
        $contactSummary = $contactValue !== '' ? $contactLabelValue : __('ui.common.all');
        $folderSummary = match ((string) ($filters['folder'] ?? 'all')) {
            'root' => __('ui.storage.folder_root'),
            'all', '' => __('ui.storage.folder_filter_all'),
            default => $folderOptions[$filters['folder'] ?? ''] ?? __('ui.storage.folder_filter_all'),
        };
    @endphp

    <section class="page-hero">
        <div class="title-with-help">
            <h1>{{ __('ui.storage.hero_title') }}</h1>
            @include('partials.inline-help', ['text' => __('ui.storage.hero_body'), 'align' => 'end'])
        </div>
        <div class="hero-actions">
            @if($storageProfile['enabled'])
                <a class="button primary" href="{{ route('files.create', ['destination' => 'storage']) }}">{{ __('ui.storage.add_file') }}</a>
            @endif
            <a class="button" href="{{ route('subscriptions.upgrade') }}">{{ __('ui.dashboard.upgrade_cta') }}</a>
        </div>
    </section>

    @unless($storageProfile['enabled'])
        <div class="status" style="margin-top: 18px;">
            {{ __('ui.storage.locked_body') }}
        </div>
    @endunless

    <section class="stats-grid" style="margin-top: 18px;">
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_files') }}</span>
            <h3>{{ __('ui.storage.files_title') }}</h3>
            <strong class="value">{{ number_format($storageProfile['files_count']) }}</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_used') }}</span>
            <h3>{{ __('ui.storage.used_title') }}</h3>
            <strong class="value">{{ number_format((int) round($storageProfile['used_bytes'] / 1024 / 1024, 1)) }} MB</strong>
        </article>
        <article class="stat-card">
            <span class="card-kicker">{{ __('ui.storage.kicker_quota') }}</span>
            <h3>{{ __('ui.storage.quota_title') }}</h3>
            <strong class="value">
                @if($storageProfile['has_unlimited_quota'])
                    {{ __('ui.storage.unlimited') }}
                @else
                    {{ number_format((int) ($storageProfile['quota_mb'] ?? 0)) }} MB
                @endif
            </strong>
        </article>
    </section>

    <section class="panel" style="margin-top: 18px;">
        <div class="section-heading">
            <div class="title-with-help">
                <h2 style="margin-bottom: 0;">{{ __('ui.storage.files_heading') }}</h2>
                @include('partials.inline-help', ['text' => __('ui.storage.files_body')])
            </div>
            @if(!$storageProfile['has_unlimited_quota'])
                <span class="badge">{{ __('ui.storage.remaining', ['size' => number_format((int) round(($storageProfile['remaining_bytes'] ?? 0) / 1024 / 1024, 1))]) }}</span>
            @endif
        </div>

        @if($storageProfile['is_full'] ?? false)
            <div class="status" style="margin-top: 18px; margin-bottom: 0;">{{ __('ui.storage.full_capacity') }}</div>
        @elseif($storageProfile['is_near_capacity'] ?? false)
            <div class="status" style="margin-top: 18px; margin-bottom: 0;">{{ __('ui.storage.near_capacity') }}</div>
        @endif

        <div class="storage-workbar">
            <div class="storage-workbar-main">
                <div class="storage-control-group storage-display-controls" aria-label="{{ __('ui.storage.view_mode') }}">
                    <a class="storage-icon-choice @if($viewMode === 'list') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'view' => 'list']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.view_list') }}" aria-label="{{ __('ui.storage.view_list') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/></svg>
                    </a>
                    <a class="storage-icon-choice @if($viewMode === 'grid') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'view' => 'grid']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.view_grid') }}" aria-label="{{ __('ui.storage.view_grid') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
                    </a>
                </div>
                <div class="storage-control-group storage-display-controls" aria-label="{{ __('ui.storage.thumbnail_size') }}">
                    <a class="storage-icon-choice thumb-sm @if($thumbnailSize === 'sm') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'sm']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_sm') }}" aria-label="{{ __('ui.storage.thumb_sm') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-md @if($thumbnailSize === 'md') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'md']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_md') }}" aria-label="{{ __('ui.storage.thumb_md') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-lg @if($thumbnailSize === 'lg') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'lg']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_lg') }}" aria-label="{{ __('ui.storage.thumb_lg') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-xl @if($thumbnailSize === 'xl') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'xl']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_xl') }}" aria-label="{{ __('ui.storage.thumb_xl') }}"><span aria-hidden="true"></span></a>
                </div>
            </div>
            @if($folderFeaturesEnabled)
                <details class="storage-folder-create">
                    <summary class="button">{{ __('ui.storage.folder_create_action') }}</summary>
                    <form method="post" action="{{ route('storage.folders.store') }}" class="storage-folder-popover">
                        @csrf
                        <div class="field">
                            <label for="storage-folder-name">{{ __('ui.storage.folder_name') }}</label>
                            <input id="storage-folder-name" name="name" value="{{ old('name') }}" placeholder="{{ __('ui.storage.folder_name_placeholder') }}">
                        </div>
                        <div class="field">
                            <label for="storage-folder-parent">{{ __('ui.storage.folder_parent') }}</label>
                            <select id="storage-folder-parent" name="parent_id">
                                <option value="">{{ __('ui.storage.folder_root') }}</option>
                                @foreach($folderOptions as $folderId => $folderLabel)
                                    <option value="{{ $folderId }}" @selected((string) old('parent_id') === (string) $folderId)>{{ $folderLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="button primary" type="submit">{{ __('ui.storage.folder_create_action') }}</button>
                    </form>
                </details>
            @endif
        </div>

        @if(($filters['contact'] ?? '') !== '')
            <div class="status" style="margin-top: 18px;">
                {{ $contactActiveLabel }}
                <a class="button" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['contact' => '', 'folder' => $filters['folder'] ?? 'all']), fn ($value) => $value !== '' && $value !== null)) }}">{{ $clearContactLabel }}</a>
            </div>
        @endif

        @if($folderFeaturesEnabled)
            <div class="panel" style="margin-top: 18px; padding: 16px;">
                <div class="section-heading" style="margin-bottom: 10px;">
                    <div>
                        <strong>{{ $folderTreeTitle }}</strong>
                        <div class="muted">{{ $folderTreeBody }}</div>
                    </div>
                </div>
                <div class="actions" style="justify-content: space-between; gap: 12px;">
                    <a class="button @if(($filters['folder'] ?? 'all') === 'all') primary @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => 'all']), fn ($value) => $value !== '' && $value !== null)) }}">{{ __('ui.storage.folder_filter_all') }}</a>
                    <a class="button @if(($filters['folder'] ?? 'all') === 'root') primary @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => 'root']), fn ($value) => $value !== '' && $value !== null)) }}">{{ __('ui.storage.folder_root') }}</a>
                </div>
                @include('storage.partials.folder-tree', ['nodes' => $folderTree ?? [], 'queryBase' => $queryBase])
            </div>
        @endif

        <div class="storage-mobile-toolbar">
            <button class="button storage-filter-toggle" type="button" data-storage-filter-open>
                <span aria-hidden="true">☰</span>
                <span>{{ __('ui.storage.open_filters') }}</span>
            </button>
            <button class="button storage-display-toggle" type="button" data-storage-display-open>
                <span aria-hidden="true">▦</span>
                <span>{{ __('ui.storage.display_options') }}</span>
            </button>
        </div>

        <div id="storage-display-panel" class="storage-display-panel" hidden>
            <div class="storage-display-header">
                <strong>{{ __('ui.storage.display_options') }}</strong>
                <button class="button" type="button" data-storage-display-close>{{ __('ui.storage.close_filters') }}</button>
            </div>
            <div class="storage-display-groups">
                <div class="storage-control-group" aria-label="{{ __('ui.storage.view_mode') }}">
                    <a class="storage-icon-choice @if($viewMode === 'list') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'view' => 'list']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.view_list') }}" aria-label="{{ __('ui.storage.view_list') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/></svg>
                    </a>
                    <a class="storage-icon-choice @if($viewMode === 'grid') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'view' => 'grid']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.view_grid') }}" aria-label="{{ __('ui.storage.view_grid') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
                    </a>
                </div>
                <div class="storage-control-group" aria-label="{{ __('ui.storage.thumbnail_size') }}">
                    <a class="storage-icon-choice thumb-sm @if($thumbnailSize === 'sm') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'sm']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_sm') }}" aria-label="{{ __('ui.storage.thumb_sm') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-md @if($thumbnailSize === 'md') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'md']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_md') }}" aria-label="{{ __('ui.storage.thumb_md') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-lg @if($thumbnailSize === 'lg') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'lg']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_lg') }}" aria-label="{{ __('ui.storage.thumb_lg') }}"><span aria-hidden="true"></span></a>
                    <a class="storage-icon-choice thumb-xl @if($thumbnailSize === 'xl') active @endif" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => $filters['folder'] ?? 'all', 'thumb' => 'xl']), fn ($value) => $value !== '' && $value !== null)) }}" title="{{ __('ui.storage.thumb_xl') }}" aria-label="{{ __('ui.storage.thumb_xl') }}"><span aria-hidden="true"></span></a>
                </div>
            </div>
        </div>

        <details id="storage-filter-accordion" class="storage-filter-accordion">
            <summary class="storage-filter-summary">
                <span class="storage-filter-title">{{ __('ui.storage.filter_panel_title') }}</span>
                <span class="storage-filter-chips">
                    <span class="badge">{{ __('ui.storage.type_label') }}: {{ $typeSummary }}</span>
                    <span class="badge">{{ __('ui.storage.scope_label') }}: {{ $scopeSummary }}</span>
                    <span class="badge">{{ __('ui.storage.period_label') }}: {{ $periodSummary }}</span>
                    <span class="badge">{{ __('ui.storage.contact_label') }}: {{ $contactSummary }}</span>
                    @if($folderFeaturesEnabled)
                        <span class="badge">{{ __('ui.storage.folder_filter_label') }}: {{ $folderSummary }}</span>
                    @endif
                </span>
            </summary>

        <form id="storage-filter-form" method="get" action="{{ route('storage.index') }}" class="grid cols-2 storage-filter-form" style="margin-top: 12px; align-items: end;">
            <div class="storage-filter-header">
                <strong>{{ __('ui.storage.filter_panel_title') }}</strong>
                <button class="button" type="button" data-storage-filter-close>{{ __('ui.storage.close_filters') }}</button>
            </div>
            <div class="field">
                <label for="storage-q">{{ __('ui.storage.search_label') }}</label>
                <input id="storage-q" name="q" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('ui.storage.search_placeholder') }}">
            </div>
            <div class="field">
                <label id="storage-type-label">{{ __('ui.storage.type_label') }}</label>
                <input id="storage-type" type="hidden" name="type" value="{{ $filters['type'] ?? 'all' }}">
                <div class="storage-icon-strip" role="group" aria-labelledby="storage-type-label">
                    <button class="storage-icon-choice @if(in_array('all', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="all" title="{{ __('ui.file_types.all') }}" aria-label="{{ __('ui.file_types.all') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('note', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="note" title="{{ __('ui.storage.note_badge') }}" aria-label="{{ __('ui.storage.note_badge') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h9l3 3v13H6z"/><path d="M14 4v4h4M9 12h6M9 16h6"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('image', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="image" title="{{ __('ui.file_types.image') }}" aria-label="{{ __('ui.file_types.image') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="m7 16 4-5 3 4 2-2 3 3"/><path d="M8.5 9h.01"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('video', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="video" title="{{ __('ui.file_types.video') }}" aria-label="{{ __('ui.file_types.video') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h12v12H4z"/><path d="m16 10 5-3v10l-5-3z"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('document', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="document" title="{{ __('ui.file_types.document') }}" aria-label="{{ __('ui.file_types.document') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h4M10 12h5M10 16h5"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('archive', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="archive" title="{{ __('ui.file_types.archive') }}" aria-label="{{ __('ui.file_types.archive') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14v13H5z"/><path d="M8 7V4h8v3M12 7v13M10 10h4M10 14h4"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(in_array('other', $selectedTypes, true)) active @endif" type="button" data-storage-choice="type" data-choice-mode="multi" data-value="other" title="{{ __('ui.file_types.other') }}" aria-label="{{ __('ui.file_types.other') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                    </button>
                </div>
            </div>
            <div class="field">
                <label for="storage-contact-search">{{ $contactLabel }}</label>
                <input id="storage-contact" type="hidden" name="contact" value="{{ $contactValue }}">
                <div class="storage-lookup-wrap">
                    <input id="storage-contact-search" class="storage-contact-filter" value="{{ $contactLabelValue }}" placeholder="{{ __('ui.storage.contact_search_placeholder') }}" autocomplete="off">
                    <div id="storage-contact-suggestions" class="storage-lookup-suggestions" hidden></div>
                </div>
                <div id="storage-contact-result" class="muted" style="margin-top: 8px;"></div>
                <div class="storage-selected-chip @if($contactValue === '') hidden @endif" data-selected-chip="contact">
                    <span>{{ __('ui.storage.selected_contact') }}: <strong data-selected-chip-label="contact">{{ $contactLabelValue }}</strong></span>
                    <button type="button" data-clear-contact-filter="contact" aria-label="{{ __('ui.storage.clear_contact') }}">×</button>
                </div>
            </div>
            <div class="field">
                <label id="storage-scope-label">{{ __('ui.storage.scope_label') }}</label>
                <input id="storage-scope" type="hidden" name="scope" value="{{ $filters['scope'] ?? 'all' }}">
                <div class="storage-icon-strip" role="group" aria-labelledby="storage-scope-label">
                    <button class="storage-icon-choice @if(($filters['scope'] ?? 'all') === 'all') active @endif" type="button" data-storage-choice="scope" data-value="all" title="{{ __('ui.storage.scope_all') }}" aria-label="{{ __('ui.storage.scope_all') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(($filters['scope'] ?? 'all') === 'owned') active @endif" type="button" data-storage-choice="scope" data-value="owned" title="{{ __('ui.storage.scope_owned') }}" aria-label="{{ __('ui.storage.scope_owned') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6z"/><path d="m9 12 2 2 4-5"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(($filters['scope'] ?? 'all') === 'sent') active @endif" type="button" data-storage-choice="scope" data-value="sent" title="{{ __('ui.storage.scope_sent') }}" aria-label="{{ __('ui.storage.scope_sent') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m6 11 6-6 6 6"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(($filters['scope'] ?? 'all') === 'received') active @endif" type="button" data-storage-choice="scope" data-value="received" title="{{ __('ui.storage.scope_received') }}" aria-label="{{ __('ui.storage.scope_received') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="m18 13-6 6-6-6"/></svg>
                    </button>
                </div>
            </div>
            @if($folderFeaturesEnabled)
                <div class="field">
                    <label for="storage-folder">{{ __('ui.storage.folder_filter_label') }}</label>
                    <select id="storage-folder" name="folder">
                        <option value="all" @selected(($filters['folder'] ?? 'all') === 'all')>{{ __('ui.storage.folder_filter_all') }}</option>
                        <option value="root" @selected(($filters['folder'] ?? 'all') === 'root')>{{ __('ui.storage.folder_root') }}</option>
                        @foreach($folderOptions as $folderId => $folderLabel)
                            <option value="{{ $folderId }}" @selected((string) ($filters['folder'] ?? 'all') === (string) $folderId)>{{ $folderLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="field">
                <label id="storage-period-label">{{ __('ui.storage.period_label') }}</label>
                <input id="storage-period" type="hidden" name="period" value="{{ $filters['period'] ?? 'all' }}">
                <div class="storage-icon-strip" role="group" aria-labelledby="storage-period-label">
                    <button class="storage-icon-choice @if(($filters['period'] ?? 'all') === 'all') active @endif" type="button" data-storage-choice="period" data-value="all" title="{{ __('ui.storage.period_all') }}" aria-label="{{ __('ui.storage.period_all') }}"><span class="storage-icon-text">{{ __('ui.common.all') }}</span></button>
                    <button class="storage-icon-choice @if(($filters['period'] ?? 'all') === 'today') active @endif" type="button" data-storage-choice="period" data-value="today" title="{{ __('ui.storage.period_today') }}" aria-label="{{ __('ui.storage.period_today') }}"><span class="storage-icon-text">{{ __('ui.storage.period_today_short') }}</span></button>
                    <button class="storage-icon-choice @if(($filters['period'] ?? 'all') === '7d') active @endif" type="button" data-storage-choice="period" data-value="7d" title="{{ __('ui.storage.period_7d') }}" aria-label="{{ __('ui.storage.period_7d') }}"><span class="storage-icon-text">7</span></button>
                    <button class="storage-icon-choice @if(($filters['period'] ?? 'all') === '30d') active @endif" type="button" data-storage-choice="period" data-value="30d" title="{{ __('ui.storage.period_30d') }}" aria-label="{{ __('ui.storage.period_30d') }}"><span class="storage-icon-text">30</span></button>
                    <button class="storage-icon-choice @if(($filters['period'] ?? 'all') === '90d') active @endif" type="button" data-storage-choice="period" data-value="90d" title="{{ __('ui.storage.period_90d') }}" aria-label="{{ __('ui.storage.period_90d') }}"><span class="storage-icon-text">90</span></button>
                </div>
            </div>
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <input type="hidden" name="thumb" value="{{ $thumbnailSize }}">
            <div class="actions" style="grid-column: 1 / -1; justify-content: space-between; align-items: center;">
                <div class="actions">
                    <button class="button primary" type="submit">{{ __('ui.common.apply_filters') }}</button>
                    <a class="button" href="{{ route('storage.index', ['view' => $viewMode, 'thumb' => $thumbnailSize]) }}">{{ __('ui.common.reset') }}</a>
                </div>
            </div>
        </form>
        </details>

        @if($files->isNotEmpty())
            <div class="muted" style="margin-top: 16px;">{{ __('ui.storage.results_count', ['count' => number_format($files->count())]) }}</div>
        @endif

        @if($viewMode === 'grid')
            <div class="grid cols-2" style="margin-top: 18px;">
                @forelse($files as $file)
                    <div class="panel" style="padding: 16px;">
                        <div class="actions" style="justify-content: space-between; align-items: flex-start;">
                            <div style="width: {{ $thumbPixels }}px; max-width: 100%;">
                                <div style="width: {{ $thumbPixels }}px; height: {{ $thumbPixels }}px; max-width: 100%; border-radius: 18px; overflow: hidden; background: rgba(15,118,110,0.06); border: 1px solid rgba(15,118,110,0.12); display:flex; align-items:center; justify-content:center; position: relative;">
                                    @if($file->getAttribute('thumbnail_url'))
                                        <img src="{{ $file->getAttribute('thumbnail_url') }}" alt="{{ $file->original_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:#0f766e;">
                                            <span style="font-size: {{ max(20, (int) floor($thumbPixels / 3.4)) }}px; line-height: 1;">{{ $file->getAttribute('thumbnail_icon') }}</span>
                                            <span class="badge">{{ $file->getAttribute('thumbnail_extension') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <details style="position: relative;">
                                <summary class="button" style="list-style:none;">...</summary>
                                <div class="panel" style="position:absolute; inset-inline-end:0; margin-top:8px; min-width:180px; z-index:5; padding:12px;">
                                    <div class="actions" style="flex-direction: column; align-items: stretch;">
                                        @if($file->getAttribute('can_inline_preview'))
                                            <a class="button" href="{{ route('storage.preview', $file) }}">{{ __('ui.actions.open_preview') }}</a>
                                        @endif
                                        <a class="button" href="{{ route('storage.download', $file) }}">{{ __('ui.actions.download') }}</a>
                                        @if($folderFeaturesEnabled && $file->getAttribute('can_manage_workspace'))
                                            <form method="post" action="{{ route('storage.folder.update', $file) }}">
                                                @csrf
                                                @method('patch')
                                                <select name="folder_id" style="width: 100%; margin-bottom: 8px;">
                                                    <option value="">{{ __('ui.storage.folder_root') }}</option>
                                                    @foreach($folderOptions as $folderId => $folderLabel)
                                                        <option value="{{ $folderId }}" @selected((string) $file->getAttribute('workspace_folder_id') === (string) $folderId)>{{ $folderLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="button" type="submit">{{ __('ui.storage.move_to_folder') }}</button>
                                            </form>
                                        @endif
                                        <button class="button" type="button" onclick="document.getElementById('storage-note-{{ $file->id }}')?.classList.toggle('hidden')">{{ __('ui.storage.show_note') }}</button>
                                        @if($file->getAttribute('can_manage_workspace'))
                                            <form method="post" action="{{ route('storage.destroy', $file) }}">
                                                @csrf
                                                @method('delete')
                                                <button class="button" type="submit">
                                                    {{ $file->getAttribute('is_workspace_owner') ? __('ui.common.delete') : __('ui.storage.remove_from_workspace') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </details>
                        </div>
                        <div class="meta-stack" style="min-width: 0; margin-top: 14px;">
                                <strong>{{ $file->original_name }}</strong>
                                <span class="muted">{{ $file->readableSize() }} &bull; {{ $file->created_at?->diffForHumans() }}</span>
                                @if($file->getAttribute('origin_sender_name'))
                                    <span class="muted">{{ __('ui.storage.sender_from', ['user' => $file->getAttribute('origin_sender_name')]) }}</span>
                                @endif
                                @if($file->getAttribute('recipient_summary'))
                                    <span class="muted">{{ __('ui.storage.recipient_to', ['user' => $file->getAttribute('recipient_summary')]) }}</span>
                                @endif
                                @if($file->getAttribute('workspace_folder_name'))
                                    <span class="muted">{{ __('ui.storage.in_folder', ['folder' => $file->getAttribute('workspace_folder_name')]) }}</span>
                                @endif
                        </div>
                        <div class="message-statuses" style="margin-top: 12px;">
                            <span class="badge">{{ __('ui.storage.scope_badge_'.$file->getAttribute('workspace_context')) }}</span>
                            <span class="badge">{{ __('ui.storage.no_expiry') }}</span>
                            @if($file->getAttribute('is_text_note'))
                                <span class="badge">{{ __('ui.storage.note_badge') }}</span>
                            @endif
                            <span class="badge">{{ __('ui.file_types.'.$file->getAttribute('storage_category')) }}</span>
                        </div>
                        @if($file->getAttribute('note_excerpt'))
                            <div id="storage-note-{{ $file->id }}" class="panel hidden" style="padding: 12px; margin-top: 12px; background: rgba(17,33,28,0.03);">
                                <div class="muted">{{ $file->getAttribute('note_excerpt') ?: __('ui.storage.note_excerpt_empty') }}</div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <span class="empty-icon">&bull;</span>
                        <strong>{{ __('ui.storage.empty_title') }}</strong>
                        <div class="muted">{{ __('ui.storage.empty_body') }}</div>
                    </div>
                @endforelse
            </div>
        @else
        <div class="list" style="margin-top: 18px;">
            @forelse($files as $file)
                <div class="item" style="align-items: flex-start;">
                    <div style="display:flex; gap:14px; min-width: 0; flex:1; align-items:flex-start;">
                        <div style="width:72px; height:72px; border-radius:16px; overflow:hidden; background: rgba(15,118,110,0.06); border:1px solid rgba(15,118,110,0.12); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            @if($file->getAttribute('thumbnail_url'))
                                <img src="{{ $file->getAttribute('thumbnail_url') }}" alt="{{ $file->original_name }}" style="width:100%; height:100%; object-fit:cover;">
                            @else
                                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; color:#0f766e;">
                                    <span style="font-size: 20px; line-height:1;">{{ $file->getAttribute('thumbnail_icon') }}</span>
                                    <span class="badge">{{ $file->getAttribute('thumbnail_extension') }}</span>
                                </div>
                            @endif
                        </div>
                    <div style="min-width: 0;">
                        <strong>{{ $file->original_name }}</strong>
                        <div class="muted">{{ $file->readableSize() }} &bull; {{ $file->created_at?->diffForHumans() }}</div>
                        @if($file->getAttribute('origin_sender_name'))
                            <div class="muted" style="margin-top: 6px;">{{ __('ui.storage.sender_from', ['user' => $file->getAttribute('origin_sender_name')]) }}</div>
                        @endif
                        @if($file->getAttribute('recipient_summary'))
                            <div class="muted" style="margin-top: 6px;">{{ __('ui.storage.recipient_to', ['user' => $file->getAttribute('recipient_summary')]) }}</div>
                        @endif
                        @if($file->getAttribute('workspace_folder_name'))
                            <div class="muted" style="margin-top: 6px;">{{ __('ui.storage.in_folder', ['folder' => $file->getAttribute('workspace_folder_name')]) }}</div>
                        @endif
                        @if($file->getAttribute('is_text_note'))
                            <div class="muted" style="margin-top: 8px;">{{ $file->getAttribute('note_excerpt') ?: __('ui.storage.note_excerpt_empty') }}</div>
                        @endif
                        <div class="message-statuses">
                            <span class="badge">{{ __('ui.storage.scope_badge_'.$file->getAttribute('workspace_context')) }}</span>
                            <span class="badge">{{ __('ui.storage.no_expiry') }}</span>
                            @if($file->getAttribute('is_text_note'))
                                <span class="badge">{{ __('ui.storage.note_badge') }}</span>
                            @endif
                            <span class="badge">{{ __('ui.file_types.'.$file->getAttribute('storage_category')) }}</span>
                            @if($file->isSecurityScanPending())
                                <span class="badge">{{ __('ui.statuses.security_pending') }}</span>
                            @elseif(!$file->isSecurityApproved())
                                <span class="badge">{{ __('ui.statuses.security_rejected') }}</span>
                            @else
                                <span class="badge">{{ __('ui.statuses.scan_clean') }}</span>
                            @endif
                        </div>
                    </div>
                    </div>
                    <div class="actions" style="justify-content: flex-end;">
                        @if($file->getAttribute('can_inline_preview'))
                            <a class="button" href="{{ route('storage.preview', $file) }}">{{ __('ui.actions.open_preview') }}</a>
                        @endif
                        <a class="button" href="{{ route('storage.download', $file) }}">{{ __('ui.actions.download') }}</a>
                        @if($file->getAttribute('can_manage_workspace'))
                            @if($folderFeaturesEnabled)
                                <form method="post" action="{{ route('storage.folder.update', $file) }}">
                                    @csrf
                                    @method('patch')
                                    <select name="folder_id">
                                        <option value="">{{ __('ui.storage.folder_root') }}</option>
                                        @foreach($folderOptions as $folderId => $folderLabel)
                                            <option value="{{ $folderId }}" @selected((string) $file->getAttribute('workspace_folder_id') === (string) $folderId)>{{ $folderLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button class="button" type="submit">{{ __('ui.storage.move_to_folder') }}</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('storage.destroy', $file) }}">
                                @csrf
                                @method('delete')
                                <button class="button" type="submit">
                                    {{ $file->getAttribute('is_workspace_owner') ? __('ui.common.delete') : __('ui.storage.remove_from_workspace') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <span class="empty-icon">&bull;</span>
                    <strong>{{ __('ui.storage.empty_title') }}</strong>
                    <div class="muted">{{ __('ui.storage.empty_body') }}</div>
                </div>
            @endforelse
        </div>
        @endif
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterForm = document.getElementById('storage-filter-form');
            const filterAccordion = document.getElementById('storage-filter-accordion');
            const openButton = document.querySelector('[data-storage-filter-open]');
            const closeButton = document.querySelector('[data-storage-filter-close]');
            const displayPanel = document.getElementById('storage-display-panel');
            const displayOpenButton = document.querySelector('[data-storage-display-open]');
            const displayCloseButton = document.querySelector('[data-storage-display-close]');
            const choiceButtons = document.querySelectorAll('[data-storage-choice]');
            const contactInput = document.getElementById('storage-contact');
            const contactSearchInput = document.getElementById('storage-contact-search');
            const contactSuggestions = document.getElementById('storage-contact-suggestions');
            const contactResult = document.getElementById('storage-contact-result');
            const clearContactButtons = document.querySelectorAll('[data-clear-contact-filter]');
            const routes = {
                usersLookup: @json(route('users.lookup')),
            };
            const translations = {
                searchNone: @json(__('ui.send.receiver_search_none')),
                searchError: @json(__('ui.send.receiver_search_error')),
                searchFound: @json(__('ui.send.receiver_search_found')),
            };
            let lookupTimer = null;
            let lookupAbortController = null;

            if (!filterForm || !openButton || !closeButton) {
                return;
            }

            const setOpen = (isOpen) => {
                if (filterAccordion) {
                    filterAccordion.open = isOpen;
                }
                filterForm.classList.toggle('is-open', isOpen);
                document.body.classList.toggle('storage-filter-open', isOpen);
            };

            openButton.addEventListener('click', () => setOpen(true));
            closeButton.addEventListener('click', () => setOpen(false));
            displayOpenButton?.addEventListener('click', () => {
                if (!displayPanel) {
                    return;
                }

                displayPanel.hidden = !displayPanel.hidden;
            });
            displayCloseButton?.addEventListener('click', () => {
                if (displayPanel) {
                    displayPanel.hidden = true;
                }
            });
            choiceButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const input = document.getElementById(`storage-${button.dataset.storageChoice}`);
                    const group = document.querySelectorAll(`[data-storage-choice="${button.dataset.storageChoice}"]`);

                    if (!input) {
                        return;
                    }

                    if (button.dataset.choiceMode === 'multi') {
                        if (button.dataset.value === 'all') {
                            input.value = 'all';
                            group.forEach((item) => item.classList.toggle('active', item === button));
                            return;
                        }

                        button.classList.toggle('active');
                        group.forEach((item) => {
                            if (item.dataset.value === 'all') {
                                item.classList.remove('active');
                            }
                        });

                        const values = Array
                            .from(group)
                            .filter((item) => item.classList.contains('active') && item.dataset.value !== 'all')
                            .map((item) => item.dataset.value)
                            .filter(Boolean);

                        if (values.length === 0) {
                            input.value = 'all';
                            group.forEach((item) => item.classList.toggle('active', item.dataset.value === 'all'));
                            return;
                        }

                        input.value = values.join(',');
                        return;
                    }

                    input.value = button.dataset.value || '';
                    group.forEach((item) => item.classList.toggle('active', item === button));
                });
            });
            const hideContactSuggestions = () => {
                if (!contactSuggestions) {
                    return;
                }

                contactSuggestions.innerHTML = '';
                contactSuggestions.hidden = true;
            };
            const syncContactChip = (value, labelText) => {
                const chip = document.querySelector('[data-selected-chip="contact"]');
                const label = document.querySelector('[data-selected-chip-label="contact"]');

                if (!contactInput || !chip || !label) {
                    return;
                }

                contactInput.value = value;
                chip.classList.toggle('hidden', value.trim() === '');
                label.textContent = labelText || value;
            };
            const renderContactSuggestions = (users) => {
                if (!contactSuggestions) {
                    return;
                }

                contactSuggestions.innerHTML = '';

                if (!Array.isArray(users) || users.length === 0) {
                    contactSuggestions.hidden = true;
                    return;
                }

                users.forEach((user) => {
                    const button = document.createElement('button');
                    const identity = user.full_name || user.username || '?';
                    const contacts = [user.email, user.mobile].filter(Boolean).join(' · ');
                    const avatar = document.createElement('span');
                    const stack = document.createElement('span');
                    const username = document.createElement('strong');
                    const fullName = document.createElement('span');
                    const contact = document.createElement('span');
                    button.type = 'button';
                    button.className = 'storage-lookup-option';
                    avatar.className = 'avatar';
                    avatar.textContent = identity.charAt(0).toUpperCase();
                    stack.className = 'meta-stack';
                    username.textContent = user.username || '';
                    fullName.className = 'muted';
                    fullName.textContent = user.full_name || '';
                    contact.className = 'muted';
                    contact.textContent = contacts;
                    stack.append(username, fullName, contact);
                    button.append(avatar, stack);
                    button.addEventListener('click', () => {
                        const username = user.username || '';
                        if (contactSearchInput) {
                            contactSearchInput.value = username;
                        }
                        syncContactChip(username, user.full_name ? `${user.full_name} (${username})` : username);
                        hideContactSuggestions();
                        if (contactResult) {
                            contactResult.textContent = '';
                        }
                    });
                    contactSuggestions.appendChild(button);
                });

                contactSuggestions.hidden = false;
            };
            const lookupContact = () => {
                if (!contactSearchInput || !contactResult) {
                    return;
                }

                const query = contactSearchInput.value.trim();
                syncContactChip('', '');

                if (lookupAbortController) {
                    lookupAbortController.abort();
                }

                clearTimeout(lookupTimer);

                if (query.length < 2) {
                    contactResult.textContent = '';
                    hideContactSuggestions();
                    return;
                }

                lookupTimer = window.setTimeout(async () => {
                    lookupAbortController = new AbortController();

                    try {
                        const response = await fetch(routes.usersLookup + '?q=' + encodeURIComponent(query), {
                            headers: {
                                Accept: 'application/json',
                            },
                            signal: lookupAbortController.signal,
                        });
                        const payload = await response.json();
                        const users = Array.isArray(payload.users) ? payload.users : [];

                        if (!payload.found || users.length === 0) {
                            contactResult.textContent = translations.searchNone;
                            hideContactSuggestions();
                            return;
                        }

                        contactResult.textContent = translations.searchFound.replace(':count', users.length);
                        renderContactSuggestions(users);
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        contactResult.textContent = translations.searchError;
                        hideContactSuggestions();
                    }
                }, 220);
            };
            contactSearchInput?.addEventListener('input', lookupContact);
            clearContactButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.dataset.clearContactFilter;
                    if (key === 'contact') {
                        syncContactChip('', '');
                        if (contactSearchInput) {
                            contactSearchInput.value = '';
                        }
                        if (contactResult) {
                            contactResult.textContent = '';
                        }
                        hideContactSuggestions();
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                    hideContactSuggestions();
                    if (displayPanel) {
                        displayPanel.hidden = true;
                    }
                }
            });
            document.addEventListener('click', (event) => {
                if (
                    contactSuggestions
                    && contactSearchInput
                    && !contactSuggestions.contains(event.target)
                    && !contactSearchInput.contains(event.target)
                ) {
                    hideContactSuggestions();
                }
            });
        });
    </script>
@endsection

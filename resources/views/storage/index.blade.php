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
            'starred' => $filters['starred'] ?? 'all',
            'period' => $filters['period'] ?? 'all',
            'sort' => $filters['sort'] ?? 'newest',
            'per_page' => $filters['per_page'] ?? 24,
            'view' => $viewMode,
            'thumb' => $thumbnailSize,
        ];
        $typeSummary = in_array('all', $selectedTypes, true)
            ? __('ui.file_types.all')
            : collect($selectedTypes)->map(fn ($type) => $type === 'note' ? __('ui.storage.note_badge') : __('ui.file_types.'.$type))->implode('، ');
        $scopeSummary = __('ui.storage.scope_'.($filters['scope'] ?? 'all'));
        $starredSummary = ($filters['starred'] ?? 'all') === 'yes' ? __('ui.storage.starred_only') : __('ui.common.all');
        $periodSummary = __('ui.storage.period_'.($filters['period'] ?? 'all'));
        $sortSummary = __('ui.storage.sort_'.($filters['sort'] ?? 'newest'));
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

    <section class="panel storage-files-panel" style="margin-top: 18px;">
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

        @if(($filters['contact'] ?? '') !== '')
            <div class="status" style="margin-top: 18px;">
                {{ $contactActiveLabel }}
                <a class="button" href="{{ route('storage.index', array_filter(array_merge($queryBase, ['contact' => '', 'folder' => $filters['folder'] ?? 'all']), fn ($value) => $value !== '' && $value !== null)) }}">{{ $clearContactLabel }}</a>
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
                    <span class="badge">{{ __('ui.storage.starred_label') }}: {{ $starredSummary }}</span>
                    <span class="badge">{{ __('ui.storage.period_label') }}: {{ $periodSummary }}</span>
                    <span class="badge">{{ __('ui.storage.sort_label') }}: {{ $sortSummary }}</span>
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
            <div class="field">
                <label id="storage-starred-label">{{ __('ui.storage.starred_label') }}</label>
                <input id="storage-starred" type="hidden" name="starred" value="{{ $filters['starred'] ?? 'all' }}">
                <div class="storage-icon-strip" role="group" aria-labelledby="storage-starred-label">
                    <button class="storage-icon-choice @if(($filters['starred'] ?? 'all') === 'all') active @endif" type="button" data-storage-choice="starred" data-value="all" title="{{ __('ui.common.all') }}" aria-label="{{ __('ui.common.all') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                    </button>
                    <button class="storage-icon-choice @if(($filters['starred'] ?? 'all') === 'yes') active @endif" type="button" data-storage-choice="starred" data-value="yes" title="{{ __('ui.storage.starred_only') }}" aria-label="{{ __('ui.storage.starred_only') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/></svg>
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
            <div class="field">
                <label for="storage-sort">{{ __('ui.storage.sort_label') }}</label>
                <select id="storage-sort" name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>{{ __('ui.storage.sort_newest') }}</option>
                    <option value="oldest" @selected(($filters['sort'] ?? 'newest') === 'oldest')>{{ __('ui.storage.sort_oldest') }}</option>
                    <option value="name_asc" @selected(($filters['sort'] ?? 'newest') === 'name_asc')>{{ __('ui.storage.sort_name_asc') }}</option>
                    <option value="name_desc" @selected(($filters['sort'] ?? 'newest') === 'name_desc')>{{ __('ui.storage.sort_name_desc') }}</option>
                    <option value="size_asc" @selected(($filters['sort'] ?? 'newest') === 'size_asc')>{{ __('ui.storage.sort_size_asc') }}</option>
                    <option value="size_desc" @selected(($filters['sort'] ?? 'newest') === 'size_desc')>{{ __('ui.storage.sort_size_desc') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="storage-per-page">{{ __('ui.storage.per_page_label') }}</label>
                <select id="storage-per-page" name="per_page">
                    <option value="12" @selected((int) ($filters['per_page'] ?? 24) === 12)>12</option>
                    <option value="24" @selected((int) ($filters['per_page'] ?? 24) === 24)>24</option>
                    <option value="48" @selected((int) ($filters['per_page'] ?? 24) === 48)>48</option>
                </select>
            </div>
            <div class="actions" style="grid-column: 1 / -1; justify-content: space-between; align-items: center;">
                <div class="actions">
                    <button class="button primary" type="submit">{{ __('ui.common.apply_filters') }}</button>
                    <a class="button" href="{{ route('storage.index', ['view' => $viewMode, 'thumb' => $thumbnailSize]) }}">{{ __('ui.common.reset') }}</a>
                </div>
            </div>
        </form>
        </details>

        @if($folderFeaturesEnabled)
            <div class="panel storage-folder-structure" style="margin-top: 18px; padding: 16px;">
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
            <div class="storage-bulk-actions">
                @if($folderFeaturesEnabled)
                    <details class="storage-folder-create">
                        <summary class="storage-workbar-action" title="{{ __('ui.storage.folder_create_action') }}" aria-label="{{ __('ui.storage.folder_create_action') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h6l2 2h8v10H4z"/><path d="M12 13h6M15 10v6"/></svg>
                        </summary>
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
                    <details class="storage-folder-create">
                        <summary class="storage-workbar-action" title="{{ __('ui.storage.bulk_move_label') }}" aria-label="{{ __('ui.storage.bulk_move_label') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h6l2 2h8v10H4z"/><path d="M7 14h9"/><path d="m13 11 3 3-3 3"/></svg>
                        </summary>
                        <form id="storage-bulk-folder-form" method="post" action="{{ route('storage.bulk.folder') }}" class="storage-folder-popover" data-storage-bulk-form>
                            @csrf
                            @method('patch')
                            <div data-storage-bulk-target></div>
                            <div class="field">
                                <label for="storage-bulk-folder">{{ __('ui.storage.move_to_folder') }}</label>
                                <select id="storage-bulk-folder" name="folder_id">
                                    <option value="">{{ __('ui.storage.folder_root') }}</option>
                                    @foreach($folderOptions as $folderId => $folderLabel)
                                        <option value="{{ $folderId }}">{{ $folderLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="storage-bulk-new-folder">{{ __('ui.storage.folder_quick_create_label') }}</label>
                                <input id="storage-bulk-new-folder" name="new_folder_name" placeholder="{{ __('ui.storage.folder_name_placeholder') }}">
                            </div>
                            <button class="button primary" type="submit">{{ __('ui.storage.bulk_move_action') }}</button>
                        </form>
                    </details>
                @endif
                <details class="storage-folder-create">
                    <summary class="storage-workbar-action danger" title="{{ __('ui.storage.bulk_delete_label') }}" aria-label="{{ __('ui.storage.bulk_delete_label') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14"/><path d="M9 7V4h6v3"/><path d="M8 10v9M12 10v9M16 10v9"/><path d="M6 7l1 14h10l1-14"/></svg>
                    </summary>
                    <form id="storage-bulk-delete-form" method="post" action="{{ route('storage.bulk.destroy') }}" class="storage-folder-popover" data-storage-bulk-form data-storage-confirm="{{ __('ui.storage.bulk_delete_confirm') }}">
                        @csrf
                        @method('delete')
                        <div data-storage-bulk-target></div>
                        <p class="muted">{{ __('ui.storage.bulk_delete_hint') }}</p>
                        <button class="button" type="submit">{{ __('ui.storage.bulk_delete_action') }}</button>
                    </form>
                </details>
            </div>
        </div>

        @if($files->total() > 0)
            <div class="muted" style="margin-top: 16px;">{{ __('ui.storage.results_count', ['count' => number_format($files->total())]) }}</div>
        @endif

        @if($viewMode === 'grid')
            <div class="grid cols-2 storage-file-grid" style="margin-top: 18px;">
                @forelse($files as $file)
                    <div class="panel storage-file-card" style="padding: 16px;">
                        <div class="storage-file-card-head">
                            <div style="width: {{ $thumbPixels }}px; max-width: 100%;">
                                @if($file->getAttribute('can_manage_workspace'))
                                    <label class="storage-bulk-check">
                                        <input type="checkbox" value="{{ $file->id }}" data-storage-bulk-file>
                                        <span>{{ __('ui.storage.bulk_select_label') }}</span>
                                    </label>
                                @endif
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
                            @include('storage.partials.file-actions', ['file' => $file, 'folderFeaturesEnabled' => $folderFeaturesEnabled, 'folderOptions' => $folderOptions])
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
                            @if($file->getAttribute('workspace_starred'))
                                <span class="badge">{{ __('ui.storage.starred_badge') }}</span>
                            @endif
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
                <div class="item storage-file-list-item" style="align-items: flex-start;">
                    <div style="display:flex; gap:14px; min-width: 0; flex:1; align-items:flex-start;">
                        @if($file->getAttribute('can_manage_workspace'))
                            <label class="storage-bulk-check">
                                <input type="checkbox" value="{{ $file->id }}" data-storage-bulk-file>
                                <span>{{ __('ui.storage.bulk_select_label') }}</span>
                            </label>
                        @endif
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
                            @if($file->getAttribute('workspace_starred'))
                                <span class="badge">{{ __('ui.storage.starred_badge') }}</span>
                            @endif
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
                    @include('storage.partials.file-actions', ['file' => $file, 'folderFeaturesEnabled' => $folderFeaturesEnabled, 'folderOptions' => $folderOptions])
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
        @if($files->hasPages())
            <div style="margin-top: 18px;">
                {{ $files->links() }}
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
            const moveOpenButtons = document.querySelectorAll('[data-storage-move-open]');
            const bulkForms = document.querySelectorAll('[data-storage-bulk-form]');
            const folderMenus = document.querySelectorAll('.storage-workbar .storage-folder-create');
            const routes = {
                usersLookup: @json(route('users.lookup')),
            };
            const translations = {
                searchNone: @json(__('ui.send.receiver_search_none')),
                searchError: @json(__('ui.send.receiver_search_error')),
                searchFound: @json(__('ui.send.receiver_search_found')),
                bulkSelectRequired: @json(__('ui.storage.bulk_select_required')),
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
            const closeFolderMenus = (except = null) => {
                folderMenus.forEach((menu) => {
                    if (menu === except) {
                        return;
                    }

                    menu.open = false;
                    menu.classList.remove('is-open');
                });
            };
            folderMenus.forEach((menu) => {
                const summary = menu.querySelector('summary');
                const popover = menu.querySelector('.storage-folder-popover');

                summary?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const shouldOpen = !menu.open;
                    closeFolderMenus(menu);
                    menu.open = shouldOpen;
                    menu.classList.toggle('is-open', shouldOpen);
                });

                popover?.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            });
            moveOpenButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const panel = document.getElementById(button.dataset.storageMoveOpen || '');

                    if (!panel) {
                        return;
                    }

                    document.querySelectorAll('.storage-move-popover').forEach((item) => {
                        if (item !== panel) {
                            item.hidden = true;
                        }
                    });

                    panel.hidden = !panel.hidden;
                });
            });
            bulkForms.forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const selectedIds = Array
                        .from(document.querySelectorAll('[data-storage-bulk-file]:checked'))
                        .map((input) => input.value)
                        .filter(Boolean);

                    form.querySelectorAll('[data-generated-bulk-file]').forEach((input) => input.remove());

                    if (selectedIds.length === 0) {
                        event.preventDefault();
                        window.alert(translations.bulkSelectRequired);
                        return;
                    }

                    if (form.dataset.storageConfirm && !window.confirm(form.dataset.storageConfirm)) {
                        event.preventDefault();
                        return;
                    }

                    const target = form.querySelector('[data-storage-bulk-target]') || form;

                    selectedIds.forEach((fileId) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'file_ids[]';
                        input.value = fileId;
                        input.dataset.generatedBulkFile = 'true';
                        target.appendChild(input);
                    });
                });
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
                    closeFolderMenus();
                    document.querySelectorAll('.storage-move-popover').forEach((item) => {
                        item.hidden = true;
                    });
                }
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.storage-folder-create')) {
                    closeFolderMenus();
                }

                document.querySelectorAll('.storage-move-popover').forEach((item) => {
                    const opener = document.querySelector(`[data-storage-move-open="${item.id}"]`);

                    if (
                        item.hidden
                        || item.contains(event.target)
                        || opener?.contains(event.target)
                    ) {
                        return;
                    }

                    item.hidden = true;
                });

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

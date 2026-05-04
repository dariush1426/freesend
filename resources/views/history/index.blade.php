@extends('layouts.app')

@section('page_title', __('ui.history.title'))

@section('content')
    @php
        $localeQuery = request()->query();
        $typeIcons = [
            'all' => '&#9675;',
            'image' => '&#9635;',
            'video' => '&#9654;',
            'document' => '&#8801;',
            'archive' => '&#8991;',
        ];
    @endphp

    <section class="panel">
        <form method="get" action="{{ route('history.index') }}" class="grid cols-3">
            <div class="field">
                <label for="q">{{ __('ui.common.search') }}</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('ui.history.search_placeholder') }}">
            </div>
            <div class="field">
                <label for="user">{{ __('ui.history.counterparty') }}</label>
                <input id="user" name="user" value="{{ $counterparty }}" placeholder="{{ __('ui.history.counterparty_placeholder') }}">
            </div>
            <div class="field">
                <label for="direction">{{ __('ui.history.direction') }}</label>
                <select id="direction" name="direction">
                    <option value="all" @selected($direction === 'all')>{{ __('ui.common.all') }}</option>
                    <option value="sent" @selected($direction === 'sent')>{{ __('ui.statuses.sent') }}</option>
                    <option value="received" @selected($direction === 'received')>{{ __('ui.statuses.received') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="status">{{ __('ui.common.status') }}</label>
                <select id="status" name="status">
                    <option value="all" @selected($status === 'all')>{{ __('ui.common.all') }}</option>
                    <option value="read" @selected($status === 'read')>{{ __('ui.statuses.read') }}</option>
                    <option value="unread" @selected($status === 'unread')>{{ __('ui.statuses.unread') }}</option>
                    <option value="downloaded" @selected($status === 'downloaded')>{{ __('ui.statuses.downloaded') }}</option>
                    <option value="not_downloaded" @selected($status === 'not_downloaded')>{{ __('ui.statuses.not_downloaded') }}</option>
                    <option value="public" @selected($status === 'public')>{{ __('ui.statuses.public') }}</option>
                    <option value="active" @selected($status === 'active')>{{ __('ui.common.active') }}</option>
                    <option value="expired" @selected($status === 'expired')>{{ __('ui.statuses.expired') }}</option>
                    <option value="deleted" @selected($status === 'deleted')>{{ __('ui.statuses.deleted') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="type">{{ __('ui.common.type') }}</label>
                <select id="type" name="type">
                    @foreach($fileTypeOptions as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ __('ui.file_types.'.$option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="align-self: end;">
                <button class="button primary" type="submit">{{ __('ui.common.apply_filters') }}</button>
                <a class="button" href="{{ route('history.index') }}">{{ __('ui.common.reset') }}</a>
            </div>
        </form>

        <div class="category-pills">
            @foreach($fileTypeOptions as $option)
                <a
                    class="category-pill {{ $type === $option ? 'active' : '' }}"
                    href="{{ route('history.index', array_merge($localeQuery, ['type' => $option])) }}"
                >
                    <span>{!! $typeIcons[$option] ?? '&#8226;' !!}</span>
                    <span>{{ __('ui.file_types.'.$option) }}</span>
                </a>
            @endforeach
        </div>

        <div class="action-bar" style="margin-top: 14px;">
            <div class="actions">
                @if($counterparty !== '')
                    <span class="soft-badge">{{ __('ui.history.filtered_user', ['user' => $counterparty]) }}</span>
                @endif
                <span class="soft-badge">{{ $history->total() }}</span>
            </div>
            <div class="actions">
                <a class="button" href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'fa' ? 'en' : 'fa']) }}">
                    {{ __('ui.locales.switch') }}: {{ app()->getLocale() === 'fa' ? __('ui.locales.en') : __('ui.locales.fa') }}
                </a>
                <a class="button primary" href="{{ route('history.index') }}">{{ __('ui.common.reset') }}</a>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top: 18px;">
        <div class="list">
            @forelse($history as $row)
                @php
                    $isSender = $row->sender_id === auth()->id();
                    $other = $isSender ? $row->receiver : $row->sender;
                    $otherLabel = $isSender
                        ? ($other?->username ?? __('ui.common.not_available'))
                        : ($other?->username ?? $row->senderDisplayName());
                    $historyRoute = $isSender || $other
                        ? route('conversations.show', $other)
                        : route('guest-file-sends.show', $row);
                    $isExpiredByTime = $row->file->expires_at && $row->file->expires_at->isPast();
                    $category = $row->file->category();
                    $directionIcon = $isSender ? '&#8599;' : '&#8601;';
                    $directionClass = match ($category) {
                        'video' => 'video',
                        'document' => 'document',
                        'archive' => 'archive',
                        default => '',
                    };
                @endphp
                <article class="item">
                    <div class="history-card" style="width: 100%;">
                        @include('partials.file-preview-card', ['send' => $row, 'previewPolicy' => $previewPolicy, 'unlockedFileIds' => $unlockedFileIds, 'showLabel' => false])
                        <div class="history-card-main">
                            <div class="mini-meta">
                                <span class="icon-badge {{ $directionClass }}">{!! $directionIcon !!}</span>
                                <strong>{{ $isSender ? __('ui.history.sent_to', ['user' => $otherLabel]) : __('ui.history.received_from', ['user' => $otherLabel]) }}</strong>
                            </div>
                            <div class="muted">{{ $row->created_at->format('Y-m-d H:i') }} · {{ __('ui.file_types.'.$category) }} · {{ $row->file->readableSize() }}</div>
                            <div class="message-statuses">
                                <span class="badge">{{ $isSender ? __('ui.statuses.sent') : __('ui.statuses.received') }}</span>
                                <span class="badge">{{ $row->read_at ? __('ui.statuses.read') : __('ui.statuses.unread') }}</span>
                                @if($row->downloaded_at)
                                    <span class="badge">{{ __('ui.statuses.downloaded') }}</span>
                                @endif
                                @if($row->public_link_enabled)
                                    <span class="badge">{{ __('ui.statuses.public') }}</span>
                                @endif
                                @if($row->file->status === \App\Models\SharedFile::STATUS_DELETED)
                                    <span class="badge">{{ __('ui.statuses.deleted') }}</span>
                                @elseif($row->file->status === \App\Models\SharedFile::STATUS_EXPIRED || $isExpiredByTime)
                                    <span class="badge">{{ __('ui.statuses.expired') }}</span>
                                @else
                                    <span class="badge">{{ __('ui.common.active') }}</span>
                                @endif
                            </div>
                            <div class="actions">
                                <a class="button" href="{{ $historyRoute }}">{{ $other ? __('ui.common.go_to_exchange') : __('ui.common.info') }}</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <span class="empty-icon">&#9675;</span>
                    <strong>{{ __('ui.history.empty_title') }}</strong>
                    <div class="muted">{{ __('ui.history.empty_body') }}</div>
                </div>
            @endforelse
        </div>

        <div style="margin-top: 14px;">
            {{ $history->links() }}
        </div>
    </section>
@endsection

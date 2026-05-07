@if(!empty($nodes))
    @php
        $folderCountLabel = app()->getLocale() === 'fa' ? 'فایل' : 'files';
    @endphp
    <div class="list" style="margin-top: 12px;">
        @foreach($nodes as $node)
            <div class="item" style="display: block;">
                <div class="actions" style="justify-content: space-between; gap: 12px;">
                    <a
                        class="button {{ $node['active'] ? 'primary' : '' }}"
                        href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => (string) $node['id']]), fn ($value) => $value !== '' && $value !== null)) }}"
                    >
                        {{ $node['name'] }}
                    </a>
                    <div class="actions" style="justify-content: flex-end;">
                        <span class="badge">{{ number_format($node['count']) }} {{ $folderCountLabel }}</span>
                        <details class="storage-folder-menu" style="position: relative;">
                            <summary class="button" style="list-style:none;">...</summary>
                            <div class="panel" style="position:absolute; inset-inline-end:0; margin-top:8px; min-width:220px; z-index:6; padding:12px;">
                                <form method="post" action="{{ route('storage.folders.update', $node['id']) }}" class="field" style="margin-bottom: 10px;">
                                    @csrf
                                    @method('patch')
                                    <label for="storage-folder-rename-{{ $node['id'] }}">{{ __('ui.storage.folder_rename_label') }}</label>
                                    <input id="storage-folder-rename-{{ $node['id'] }}" name="name" value="{{ $node['name'] }}">
                                    <button class="button" type="submit" style="margin-top: 8px;">{{ __('ui.storage.folder_rename_action') }}</button>
                                </form>
                                @if(($node['count'] ?? 0) < 1 && empty($node['children']))
                                    <form method="post" action="{{ route('storage.folders.destroy', $node['id']) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="button" type="submit">{{ __('ui.storage.folder_delete_action') }}</button>
                                    </form>
                                @else
                                    <span class="muted">{{ __('ui.storage.folder_delete_disabled') }}</span>
                                @endif
                            </div>
                        </details>
                    </div>
                </div>
                @if(!empty($node['children']))
                    <div style="padding-inline-start: 18px;">
                        @include('storage.partials.folder-tree', ['nodes' => $node['children'], 'queryBase' => $queryBase])
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

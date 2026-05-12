@if(!empty($nodes))
    @php
        $folderCountLabel = app()->getLocale() === 'fa' ? 'فایل' : 'files';
    @endphp
    <div class="list" style="margin-top: 12px;">
        @foreach($nodes as $node)
            <div class="item storage-folder-node" style="display: block;">
                <div class="actions" style="justify-content: space-between; gap: 12px;">
                    <a
                        class="button {{ $node['active'] ? 'primary' : '' }}"
                        href="{{ route('storage.index', array_filter(array_merge($queryBase, ['folder' => (string) $node['id']]), fn ($value) => $value !== '' && $value !== null)) }}"
                    >
                        {{ $node['name'] }}
                    </a>
                    <div class="actions" style="justify-content: flex-end;">
                        <span class="badge">{{ number_format($node['count']) }} {{ $folderCountLabel }}</span>
                        <details class="storage-folder-menu">
                            <summary class="storage-file-action" title="{{ __('ui.storage.folder_actions') }}" aria-label="{{ __('ui.storage.folder_actions') }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                            </summary>
                            <div class="storage-folder-menu-popover">
                                <form method="post" action="{{ route('storage.folders.update', $node['id']) }}" class="field" style="margin-bottom: 10px;">
                                    @csrf
                                    @method('patch')
                                    <label for="storage-folder-rename-{{ $node['id'] }}">{{ __('ui.storage.folder_rename_label') }}</label>
                                    <input id="storage-folder-rename-{{ $node['id'] }}" name="name" value="{{ $node['name'] }}">
                                    <label for="storage-folder-parent-{{ $node['id'] }}" style="margin-top: 8px;">{{ __('ui.storage.folder_parent') }}</label>
                                    <select id="storage-folder-parent-{{ $node['id'] }}" name="parent_id">
                                        <option value="">{{ __('ui.storage.folder_root') }}</option>
                                        @foreach($folderOptions as $folderId => $folderLabel)
                                            @if((string) $folderId !== (string) $node['id'])
                                                <option value="{{ $folderId }}" @selected((string) ($node['parent_id'] ?? '') === (string) $folderId)>{{ $folderLabel }}</option>
                                            @endif
                                        @endforeach
                                    </select>
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

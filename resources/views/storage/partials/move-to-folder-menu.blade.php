<div class="storage-move-menu">
    <button class="{{ ($iconOnly ?? false) ? 'storage-file-action' : 'button' }}" type="button" data-storage-move-open="storage-move-{{ $file->id }}" title="{{ __('ui.storage.move_to_folder') }}" aria-label="{{ __('ui.storage.move_to_folder') }}">
        @if($iconOnly ?? false)
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h6l2 2h8v10H4z"/><path d="M12 12h6"/><path d="m15 9 3 3-3 3"/></svg>
        @else
            {{ __('ui.storage.move_to_folder') }}
        @endif
    </button>
    <div id="storage-move-{{ $file->id }}" class="storage-move-popover" hidden>
        <form method="post" action="{{ route('storage.folder.update', $file) }}">
            @csrf
            @method('patch')

            <div class="field">
                <label for="storage-move-folder-{{ $file->id }}">{{ __('ui.storage.folder_select_label') }}</label>
                <select id="storage-move-folder-{{ $file->id }}" name="folder_id" class="storage-folder-select">
                    <option value="" @selected($file->getAttribute('workspace_folder_id') === null)>{{ __('ui.storage.folder_root') }}</option>
                    @foreach($folderOptions as $folderId => $folderLabel)
                        <option value="{{ $folderId }}" @selected((string) $file->getAttribute('workspace_folder_id') === (string) $folderId)>{{ $folderLabel }}</option>
                    @endforeach
                </select>
                @if(empty($folderOptions))
                    <div class="muted" style="margin-top: 8px;">{{ __('ui.storage.folder_empty_hint') }}</div>
                @endif
            </div>

            <div class="field" style="margin-top: 12px;">
                <label for="storage-new-folder-{{ $file->id }}">{{ __('ui.storage.folder_quick_create_label') }}</label>
                <input id="storage-new-folder-{{ $file->id }}" name="new_folder_name" placeholder="{{ __('ui.storage.folder_name_placeholder') }}">
            </div>

            <button class="button primary" type="submit">{{ __('ui.storage.move_to_folder') }}</button>
        </form>
    </div>
</div>

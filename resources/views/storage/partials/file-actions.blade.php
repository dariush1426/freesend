<div class="storage-file-actions" aria-label="{{ __('ui.storage.file_actions') }}">
    <form method="post" action="{{ route('storage.star', $file) }}">
        @csrf
        @method('patch')
        <button class="storage-file-action @if($file->getAttribute('workspace_starred')) active @endif" type="submit" title="{{ $file->getAttribute('workspace_starred') ? __('ui.storage.unstar_action') : __('ui.storage.star_action') }}" aria-label="{{ $file->getAttribute('workspace_starred') ? __('ui.storage.unstar_action') : __('ui.storage.star_action') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/></svg>
        </button>
    </form>

    @if($file->getAttribute('can_inline_preview'))
        <a class="storage-file-action" href="{{ route('storage.preview', $file) }}" title="{{ __('ui.actions.open_preview') }}" aria-label="{{ __('ui.actions.open_preview') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><path d="M12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
        </a>
    @endif

    <a class="storage-file-action" href="{{ route('storage.download', $file) }}" title="{{ __('ui.actions.download') }}" aria-label="{{ __('ui.actions.download') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/></svg>
    </a>

    @if($file->getAttribute('note_excerpt'))
        <button class="storage-file-action" type="button" onclick="document.getElementById('storage-note-{{ $file->id }}')?.classList.toggle('hidden')" title="{{ __('ui.storage.show_note') }}" aria-label="{{ __('ui.storage.show_note') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h9l3 3v13H6z"/><path d="M14 4v4h4M9 12h6M9 16h5"/></svg>
        </button>
    @endif

    @if($folderFeaturesEnabled && $file->getAttribute('can_manage_workspace'))
        @include('storage.partials.move-to-folder-menu', ['file' => $file, 'folderOptions' => $folderOptions, 'iconOnly' => true])
    @endif

    @if($file->getAttribute('can_manage_workspace'))
        <details class="storage-rename-menu">
            <summary class="storage-file-action" title="{{ __('ui.storage.file_rename_action') }}" aria-label="{{ __('ui.storage.file_rename_action') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16z"/><path d="m14 6 4 4"/></svg>
            </summary>
            <div class="storage-action-popover">
                @include('storage.partials.rename-file-form', ['file' => $file])
            </div>
        </details>

        <form method="post" action="{{ route('storage.destroy', $file) }}">
            @csrf
            @method('delete')
            <button class="storage-file-action danger" type="submit" title="{{ $file->getAttribute('is_workspace_owner') ? __('ui.common.delete') : __('ui.storage.remove_from_workspace') }}" aria-label="{{ $file->getAttribute('is_workspace_owner') ? __('ui.common.delete') : __('ui.storage.remove_from_workspace') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14"/><path d="M9 7V4h6v3"/><path d="M8 10v9M12 10v9M16 10v9"/><path d="M6 7l1 14h10l1-14"/></svg>
            </button>
        </form>
    @endif
</div>

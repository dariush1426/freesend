<form method="post" action="{{ route('storage.rename', $file) }}" class="storage-rename-form">
    @csrf
    @method('patch')
    <label for="storage-rename-{{ $file->id }}">{{ __('ui.storage.file_rename_label') }}</label>
    <input id="storage-rename-{{ $file->id }}" name="name" value="{{ $file->original_name }}" maxlength="255">
    <button class="button" type="submit">{{ __('ui.storage.file_rename_action') }}</button>
</form>

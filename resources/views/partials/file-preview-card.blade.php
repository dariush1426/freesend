@php
    $previewPolicy = $previewPolicy ?? \App\Support\FilePreviewPolicy::fromSettings();
    $unlockedFileIds = $unlockedFileIds ?? [];
    $file = $send->file;
    $category = $file->category();
    $previewType = \App\Support\FilePreviewPolicy::detectType($file, $previewPolicy);
    $canPreviewByPolicy = \App\Support\FilePreviewPolicy::canPreview($file, $previewPolicy);
    $isUnlocked = in_array($file->id, $unlockedFileIds, true);
    $canPreviewNow = $canPreviewByPolicy && (! $file->isPasswordProtected() || $isUnlocked);
    $showLabel = $showLabel ?? true;
    $glyph = match ($category) {
        'image' => '▣',
        'video' => '▶',
        'document' => '≣',
        'archive' => '⌁',
        default => '•',
    };
    $label = __('ui.file_types.'.$category);
    $fileExtension = strtoupper((string) ($file->extension ?: __('ui.file_types.generic_short')));
@endphp

<div class="file-preview-card file-preview-{{ $category }}">
    <div class="file-preview-frame">
        @if($canPreviewNow && $previewType === 'image')
            <img
                class="file-preview-image"
                src="{{ route('file-sends.preview', $send) }}"
                alt="{{ $file->original_name }}"
                loading="lazy"
            >
        @else
            <div class="file-preview-fallback">
                <span class="file-preview-glyph" aria-hidden="true">{{ $glyph }}</span>
                <span class="file-preview-ext">{{ $fileExtension }}</span>
            </div>
        @endif
    </div>
    @if($showLabel)
        <div class="file-preview-caption">
            <span class="badge">{{ $label }}</span>
        </div>
    @endif
</div>

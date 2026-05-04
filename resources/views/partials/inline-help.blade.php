@php
    $text = $text ?? '';
    $align = $align ?? 'start';
    $label = $label ?? __('ui.common.info');
    $maxWidth = $maxWidth ?? '280px';
@endphp

@if($text !== '')
    <details class="inline-help {{ $align === 'end' ? 'align-end' : '' }}">
        <summary class="inline-help-toggle" aria-label="{{ $label }}">i</summary>
        <div class="inline-help-panel" style="max-width: {{ $maxWidth }};">{{ $text }}</div>
    </details>
@endif

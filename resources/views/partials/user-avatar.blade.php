@php
    $avatarUser = $user ?? null;
    $avatarClass = trim('avatar '.($class ?? ''));
    $avatarAlt = $avatarUser?->full_name ?: $avatarUser?->username ?: __('ui.layout.profile');
@endphp

@if($avatarUser?->avatarUrl())
    <img class="{{ $avatarClass }}" src="{{ $avatarUser->avatarUrl() }}" alt="{{ $avatarAlt }}">
@else
    <span class="{{ $avatarClass }}">{{ $avatarUser?->displayInitial() ?? '?' }}</span>
@endif

@php
    $plan = $plan ?? null;
    $method = $method ?? null;
    $action = $action ?? route('admin.plans.store');
    $submitLabel = $submitLabel ?? __('admin.buttons.create_plan');
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if($method)
        @method($method)
    @endif

    <div class="grid cols-3">
        <div class="field">
            <label for="plan-name">{{ __('admin.plan_fields.name') }}</label>
            <input id="plan-name" name="name" value="{{ old('name', $plan?->name) }}" required>
        </div>
        <div class="field">
            <label for="plan-slug">{{ __('admin.plan_fields.slug') }}</label>
            <input id="plan-slug" name="slug" value="{{ old('slug', $plan?->slug) }}" placeholder="premium-plus">
        </div>
        <div class="field">
            <label for="plan-sort-order">{{ __('admin.plan_fields.sort_order') }}</label>
            <input id="plan-sort-order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}">
        </div>
    </div>

    <div class="field">
        <label for="plan-description">{{ __('admin.plan_fields.description') }}</label>
        <textarea id="plan-description" name="description">{{ old('description', $plan?->description) }}</textarea>
    </div>

    <div class="grid cols-3">
        <div class="field">
            <label for="plan-price">{{ __('admin.plan_fields.price_amount') }}</label>
            <input id="plan-price" name="price_amount" type="number" min="0" value="{{ old('price_amount', $plan?->price_amount ?? 0) }}">
        </div>
        <div class="field">
            <label for="plan-duration-value">{{ __('admin.plan_fields.duration_value') }}</label>
            <input id="plan-duration-value" name="duration_value" type="number" min="1" value="{{ old('duration_value', $plan?->duration_value) }}">
        </div>
        <div class="field">
            <label for="plan-duration-unit">{{ __('admin.plan_fields.duration_unit') }}</label>
            <select id="plan-duration-unit" name="duration_unit">
                <option value="">{{ __('ui.common.not_available') }}</option>
                <option value="day" @selected(old('duration_unit', $plan?->duration_unit) === 'day')>{{ __('admin.options.duration_unit.day') }}</option>
                <option value="month" @selected(old('duration_unit', $plan?->duration_unit) === 'month')>{{ __('admin.options.duration_unit.month') }}</option>
            </select>
        </div>
    </div>

    <div class="grid cols-3">
        <div class="field">
            <label for="plan-max-upload">{{ __('admin.plan_fields.max_upload_size_mb') }}</label>
            <input id="plan-max-upload" name="max_upload_size_mb" type="number" min="1" value="{{ old('max_upload_size_mb', $plan?->max_upload_size_mb) }}">
        </div>
        <div class="field">
            <label for="plan-max-storage">{{ __('admin.plan_fields.max_storage_mb') }}</label>
            <input id="plan-max-storage" name="max_storage_mb" type="number" min="1" value="{{ old('max_storage_mb', $plan?->max_storage_mb) }}">
        </div>
        <div class="field">
            <label for="plan-max-team">{{ __('admin.plan_fields.max_team_members') }}</label>
            <input id="plan-max-team" name="max_team_members" type="number" min="1" value="{{ old('max_team_members', $plan?->max_team_members) }}">
        </div>
    </div>

    <div class="field">
        <label>{{ __('admin.plans.expire_options') }}</label>
        <div class="grid cols-4">
            @foreach($expireOptionValues as $expireOptionValue)
                <label class="checkbox-card">
                    <input
                        type="checkbox"
                        name="expire_options[]"
                        value="{{ $expireOptionValue }}"
                        @checked(in_array($expireOptionValue, old('expire_options', $plan?->expire_options ?? ['default', '1', '2', '5', '12', '24', 'custom']), true))
                    >
                    <span>{{ $expireOptionValue }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label>{{ __('admin.plans.features') }}</label>
        <div class="grid cols-3">
            @foreach([
                'is_active',
                'is_default',
                'allow_public_links',
                'allow_password_protection',
                'allow_custom_expiry',
                'allow_never_expire',
                'allow_personal_storage',
                'allow_team_features',
                'allow_signature_workflow',
                'allow_folders',
                'allow_ai_features',
            ] as $toggle)
                <label class="checkbox-card">
                    <input
                        type="checkbox"
                        name="{{ $toggle }}"
                        value="1"
                        @checked((bool) old($toggle, $plan ? $plan->{$toggle} : in_array($toggle, ['is_active', 'allow_public_links', 'allow_password_protection', 'allow_custom_expiry'], true)))
                    >
                    <span>{{ __('admin.plan_toggles.'.$toggle) }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <button class="button primary" type="submit">{{ $submitLabel }}</button>
</form>

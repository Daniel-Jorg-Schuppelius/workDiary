{{-- Erwartet: $warehouse (Warehouse|null), $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $warehouse ? route('warehouses.update', $warehouse) : route('warehouses.store');
    $dialogUrl = ($warehouse ? route('warehouses.edit', $warehouse) : route('warehouses.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$warehouse ? __('inventory.action.edit_warehouse') : __('inventory.action.create_warehouse')"
    :eyebrow="__('inventory.warehouses')"
    icon="warehouse"
    tone="primary"
    :action="$action"
    :method="$warehouse ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$warehouse ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="warehouse" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Name') }} *</label>
            <input name="name" type="text" required maxlength="255"
                   class="input input-bordered w-full" value="{{ old('name', $warehouse?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('inventory.field.code') }}</label>
            <input name="code" type="text" maxlength="40"
                   class="input input-bordered w-full" value="{{ old('code', $warehouse?->code) }}">
            @error('code')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('inventory.field.location_note') }}</label>
            <input name="location_note" type="text" maxlength="255"
                   class="input input-bordered w-full" value="{{ old('location_note', $warehouse?->location_note) }}">
        </div>
    </x-form-group>

    <x-form-group :legend="__('Status')" icon="tune" tone="primary" cols="3">
        @foreach (['is_default' => __('inventory.field.default'), 'active' => __('article.status.active'), 'blocked' => __('inventory.state.blocked')] as $key => $label)
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1" class="checkbox checkbox-sm"
                       @checked(old($key, $warehouse ? $warehouse->{$key} : ($key === 'active')))>
                <span class="label-text">{{ $label }}</span>
            </label>
        @endforeach
    </x-form-group>
</x-modal>

{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $warehouse (Warehouse), $bin (WarehouseBin|null), $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $bin ? route('warehouses.bins.update', [$warehouse, $bin]) : route('warehouses.bins.store', $warehouse);
    $dialogUrl = ($bin ? route('warehouses.bins.edit', [$warehouse, $bin]) : route('warehouses.bins.create', $warehouse)) . '?dialog=1';
@endphp

<x-modal
    :title="$bin ? __('inventory.action.edit_bin') : __('inventory.action.create_bin')"
    :eyebrow="$warehouse->name"
    icon="shelves"
    tone="primary"
    :action="$action"
    :method="$bin ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$bin ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="shelves" tone="primary" cols="2">
        <x-input-field name="code" :label="__('inventory.field.code')" required maxlength="40" :value="old('code', $bin?->code)" />
        <x-input-field name="sort_order" type="number" min="0" max="65535" :label="__('inventory.field.sort_order')" :value="old('sort_order', $bin?->sort_order ?? 0)" />
        <x-input-field name="name" :label="__('Name')" maxlength="255" :value="old('name', $bin?->name)" :span="2" />
    </x-form-group>

    <x-form-group :legend="__('Status')" icon="tune" tone="primary" cols="2">
        @foreach (['active' => __('article.status.active'), 'blocked' => __('inventory.state.blocked')] as $key => $label)
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1" class="checkbox checkbox-sm"
                       @checked(old($key, $bin ? $bin->{$key} : ($key === 'active')))>
                <span class="label-text">{{ $label }}</span>
            </label>
        @endforeach
    </x-form-group>
</x-modal>

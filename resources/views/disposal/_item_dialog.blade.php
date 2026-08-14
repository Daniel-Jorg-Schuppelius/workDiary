{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Geräteposition erfassen/bearbeiten (Feature 100, MVP-474).
     Erwartet: $job, $item (DisposalItem|null = Anlegen), $wasteCodes.
     Lokaler ad-hoc-Dialog (:embedded="false"), geöffnet via data-open-dialog. --}}
@php
    $dialogId = $item !== null ? 'disposal-item-edit-' . $item->id : 'disposal-item-create';
@endphp
<x-modal
    :id="$dialogId"
    :embedded="false"
    :title="$item !== null ? __('disposal.item.title_edit') : __('disposal.item.title_create')"
    :eyebrow="__('disposal.eyebrow')"
    icon="devices"
    tone="primary"
    :badge="$item?->category"
    :action="$item !== null ? route('disposal.items.update', $item) : route('disposal.items.store', $job)"
    :method="$item !== null ? 'PUT' : 'POST'"
    :submit-label="$item !== null ? __('Speichern') : __('Erfassen')"
>
    <x-form-group :legend="__('disposal.item.group_device')" icon="devices" tone="primary" cols="2">
        <x-input-field name="category" :label="__('Kategorie')" required maxlength="120"
                       :value="$item?->category ?? old('category')" />
        <x-input-field name="manufacturer" :label="__('Hersteller')" maxlength="120"
                       :value="$item?->manufacturer ?? old('manufacturer')" />
        <x-input-field name="model" :label="__('Modell')" maxlength="120"
                       :value="$item?->model ?? old('model')" />
        <x-input-field name="serial_number" :label="__('Seriennummer')" maxlength="120"
                       :value="$item?->serial_number ?? old('serial_number')" />
        <x-input-field name="quantity" type="number" min="1" step="1" :label="__('Menge')" required
                       :value="$item?->quantity ?? old('quantity', 1)" />
        <x-input-field name="weight_kg" type="number" min="0" step="0.001" :label="__('disposal.item.weight')"
                       :value="$item?->weight_kg ?? old('weight_kg')" />
        <x-input-field name="condition_note" :label="__('disposal.item.condition_note')" maxlength="180" span="2"
                       :value="$item?->condition_note ?? old('condition_note')" />
    </x-form-group>

    <x-form-group :legend="__('disposal.item.group_disposal')" icon="recycling" tone="primary" cols="2">
        <x-input-field name="avv_code" :label="__('disposal.item.avv_code')" required maxlength="12"
                       list="{{ $dialogId }}-avv"
                       :value="$item?->avv_code ?? old('avv_code')"
                       :hint="__('disposal.item.avv_hint')" />
        <datalist id="{{ $dialogId }}-avv">
            @foreach ($wasteCodes as $code)
                <option value="{{ \Illuminate\Support\Str::before($code->label, ' — ') }}">{{ $code->label }}</option>
            @endforeach
        </datalist>
        <x-checkbox-field name="has_data_storage" :label="__('disposal.item.has_data_storage')"
                          :checked="(bool) ($item?->has_data_storage)" />
        <x-textarea-field name="note" :label="__('disposal.item.note')" rows="2" span="2">{{ $item?->note ?? old('note') }}</x-textarea-field>
    </x-form-group>
</x-modal>

{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $presetAssetId (nullable), $isDialog
--}}
@php
    $isDialog = $isDialog ?? false;
    $presetAssetId = $presetAssetId ?? null;
    $dialogUrl = route('meter-readings.create', array_filter(['asset' => $presetAssetId]));
@endphp

<x-modal
    :title="__('Zählerstand erfassen')"
    :eyebrow="__('Zählerstand')"
    icon="speed"
    tone="primary"
    :action="route('meter-readings.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Erfassen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Ablesung')" icon="speed" tone="primary" cols="2">
        <x-input-field name="asset_id" type="number" min="1" :label="__('Asset (Zähler)')" required
                       :value="old('asset_id', $presetAssetId)" />
        <x-input-field name="read_at" type="datetime-local" :label="__('Ablesezeitpunkt')"
                       :value="old('read_at')" />
        <x-input-field name="value" type="number" step="0.0001" :label="__('Stand')" required
                       :value="old('value')" />
        <x-input-field name="unit" :label="__('Einheit')" required maxlength="32"
                       :value="old('unit', 'kWh')" />
        <x-checkbox-field name="is_estimated" span="2" :label="__('Geschätzter Wert')"
                          :toggle="false" :checked="old('is_estimated')" />
    </x-form-group>

    <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
        <x-textarea-field name="notes" :label="__('Notiz')" rows="3" maxlength="5000"
                          :value="old('notes')" />
    </x-form-group>
</x-modal>

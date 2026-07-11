{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $directionOptions, $presetAssetId (nullable), $isDialog
--}}
@php
    $isDialog = $isDialog ?? false;
    $presetAssetId = $presetAssetId ?? null;
    $dialogUrl = route('key-handovers.create', array_filter(['asset' => $presetAssetId]));
@endphp

<x-modal
    :title="__('Schlüsselvorgang erfassen')"
    :eyebrow="__('Schlüsselvorgang')"
    icon="key"
    tone="primary"
    :action="route('key-handovers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Erfassen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Vorgang')" icon="key" tone="primary" cols="2">
        <x-input-field name="asset_id" type="number" min="1" :label="__('Asset (Schlüssel)')" required
                       :value="old('asset_id', $presetAssetId)" />
        <x-select-field name="direction" :label="__('Richtung')" required>
            @foreach ($directionOptions as $val => $label)
                <option value="{{ $val }}" @selected(old('direction') === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="person_name" :label="__('Person')" required maxlength="200"
                       :value="old('person_name')" />
        <x-input-field name="person_reference" :label="__('Referenz (Ausweis-Nr., Vertrag …)')" maxlength="200"
                       :value="old('person_reference')" />
        <x-input-field name="occurred_at" type="datetime-local" :label="__('Zeitpunkt')"
                       :value="old('occurred_at')" />
        <x-input-field name="expected_return_at" type="date" :label="__('Rückgabe erwartet bis')"
                       :value="old('expected_return_at')" />
    </x-form-group>

    <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
        <x-textarea-field name="notes" :label="__('Notiz')" rows="3" maxlength="5000"
                          :value="old('notes')" />
    </x-form-group>
</x-modal>

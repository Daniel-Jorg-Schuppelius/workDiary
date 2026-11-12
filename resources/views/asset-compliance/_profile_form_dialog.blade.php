{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _profile_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\AssetCompliance\AssetComplianceProfile $profile */
@endphp

<x-modal
    :title="__('asset_compliance.profile.edit')"
    :eyebrow="$profile->code"
    icon="rule_settings"
    tone="primary"
    size="lg"
    :action="route('asset-compliance.profiles.update', $profile)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="name" :label="__('Name')" required maxlength="255" :value="old('name', $profile->name)" />
        <x-select-field name="inspection_kind" :label="__('Prüfart')" required>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('inspection_kind', $profile->inspection_kind->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="interval_months" type="number" min="1" max="240" :label="__('Intervall (Monate)')" required
                       :value="old('interval_months', $profile->interval_months)" :hint="__('asset_compliance.profile.interval_hint')" />
        <x-select-field name="blocking_mode" :label="__('Sperrwirkung')" required>
            @foreach ($blockModes as $mode)
                <option value="{{ $mode->value }}" @selected(old('blocking_mode', $profile->blocking_mode->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="warn_days_before" type="number" min="0" max="365" :label="__('Vorwarnzeit (Tage)')" :value="old('warn_days_before', $profile->warn_days_before)" />
        <x-input-field name="tolerance_days" type="number" min="0" max="365" :label="__('Toleranz (Tage)')" :value="old('tolerance_days', $profile->tolerance_days)" />
        <x-input-field name="grace_days" type="number" min="0" max="365" :label="__('Nachfrist (Tage)')" :value="old('grace_days', $profile->grace_days)" />
        <x-input-field name="default_authority" :label="__('Standard-Prüfstelle')" maxlength="255" :value="old('default_authority', $profile->default_authority)" />
    </div>

    <x-checkbox-field name="requires_certificate" :label="__('Zertifikatsnachweis Pflicht')" :checked="(bool) old('requires_certificate', $profile->requires_certificate)" />

    <x-textarea-field name="description" :label="__('Beschreibung')" rows="2" maxlength="4000" :value="old('description', $profile->description)" />
</x-modal>

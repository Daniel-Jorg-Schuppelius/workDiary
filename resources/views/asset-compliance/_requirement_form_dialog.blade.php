{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _requirement_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\AssetCompliance\AssetComplianceProfile $profile */
    /** @var \App\Models\AssetCompliance\AssetComplianceRequirement $requirement */
@endphp

<x-modal
    :title="__('asset_compliance.requirement.edit')"
    :eyebrow="$profile->name"
    icon="straighten"
    tone="primary"
    size="md"
    :action="route('asset-compliance.profiles.requirements.update', [$profile, $requirement])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <p class="mb-2 text-sm text-base-content/70">{{ __('asset_compliance.requirement.edit_hint') }}</p>

    <x-input-field name="label" :label="__('Anforderung')" required maxlength="255" :value="old('label', $requirement->label)" />

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="limit_min" type="number" step="0.0001" :label="__('Min')" :value="old('limit_min', $requirement->limit_min)" />
        <x-input-field name="limit_max" type="number" step="0.0001" :label="__('Max')" :value="old('limit_max', $requirement->limit_max)" />
        <x-input-field name="unit" :label="__('Einheit')" maxlength="30" :value="old('unit', $requirement->unit)" />
    </div>

    <x-checkbox-field name="is_mandatory" :label="__('Pflicht')" :checked="(bool) old('is_mandatory', $requirement->is_mandatory)" />
</x-modal>

{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _authority_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Angaben zur Behördenmeldung (MVP-945). Erwartet: $recall --}}
<x-modal
    :title="__('recall.authority.title')"
    :eyebrow="$recall->number"
    icon="gavel"
    tone="primary"
    :action="route('recalls.authority.update', $recall)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('recall.authority.save')"
>
    <x-form-group :legend="__('recall.authority.section.hazard')" icon="warning" tone="primary" cols="2">
        <x-input-field name="hazard_kind" :label="__('recall.authority.field.hazard_kind')" :value="old('hazard_kind', $recall->hazard_kind)" :hint="__('recall.authority.hint.hazard_kind')" />
        <x-select-field name="risk_level" :label="__('recall.authority.field.risk_level')">
            <option value="">—</option>
            @foreach (\App\Enums\Inventory\RecallRiskLevel::cases() as $level)
                <option value="{{ $level->value }}" @selected(old('risk_level', $recall->risk_level?->value) === $level->value)>{{ $level->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="hazard_description" :label="__('recall.authority.field.hazard_description')" rows="3" span="2">{{ old('hazard_description', $recall->hazard_description) }}</x-textarea-field>
        <x-select-field name="measure" :label="__('recall.authority.field.measure')">
            <option value="">—</option>
            @foreach (\App\Enums\Inventory\RecallMeasure::cases() as $measure)
                <option value="{{ $measure->value }}" @selected(old('measure', $recall->measure?->value) === $measure->value)>{{ $measure->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="countries" :label="__('recall.authority.field.countries')" :value="old('countries', implode(', ', (array) $recall->countries))" :hint="__('recall.authority.hint.countries')" />
    </x-form-group>
    <x-form-group :legend="__('recall.authority.section.authority')" icon="gavel" tone="primary" cols="2">
        <x-input-field name="authority_name" :label="__('recall.authority.field.authority_name')" :value="old('authority_name', $recall->authority_name)" />
        <x-input-field name="authority_reference" :label="__('recall.authority.field.authority_reference')" :value="old('authority_reference', $recall->authority_reference)" />
        <x-input-field name="authority_reported_on" type="date" :label="__('recall.authority.field.authority_reported_on')" :value="old('authority_reported_on', $recall->authority_reported_on?->toDateString())" />
        <x-input-field name="contact_name" :label="__('recall.authority.field.contact_name')" :value="old('contact_name', $recall->contact_name)" />
        <x-input-field name="contact_email" type="email" :label="__('recall.authority.field.contact_email')" :value="old('contact_email', $recall->contact_email)" />
    </x-form-group>
</x-modal>

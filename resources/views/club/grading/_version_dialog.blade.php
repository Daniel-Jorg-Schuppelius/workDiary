{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _version_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Regelversion bearbeiten (in #entry-modal geladen). Variablen: $system, $version --}}
<x-modal
    :title="__('club.grading.action.edit_version', ['no' => $version->version_no])"
    :eyebrow="$system->name"
    icon="history"
    tone="primary"
    :action="route('club.grading.versions.update', $version)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.card.versions')" icon="history" tone="primary" cols="2" :description="__('club.grading.hint.version')">
        <x-input-field name="valid_from" type="date" :label="__('club.field.valid_from')" :value="old('valid_from', $version->valid_from?->toDateString())" />
        <x-input-field name="unit_minutes" type="number" min="1" max="600" :label="__('club.grading.field.unit_minutes')" :value="old('unit_minutes', $version->unit_minutes)" :hint="__('club.grading.hint.unit_minutes')" />
        <x-checkbox-field name="accepts_external_credits" :label="__('club.grading.field.accepts_external_credits')" :checked="(bool) old('accepts_external_credits', $version->accepts_external_credits)" span="2" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="3" maxlength="2000" span="2" :value="old('notes', $version->notes)" />
    </x-form-group>
</x-modal>

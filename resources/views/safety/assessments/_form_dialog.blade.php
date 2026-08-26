{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kopf-Dialog Gefährdungsbeurteilung (in #entry-modal geladen).
  Variablen: $assessment (HazardAssessment|null)
--}}
@php
    $isEdit = $assessment !== null;
@endphp

<x-modal
    :title="$isEdit ? __('safety.register.action.edit') : __('safety.register.action.create_assessment')"
    :eyebrow="__('safety.register.title.assessments')"
    icon="checklist"
    tone="primary"
    :action="$isEdit ? route('safety.assessments.update', $assessment) : route('safety.assessments.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('safety.register.action.save') : __('safety.register.action.create_assessment')">

    <x-form-group :legend="__('safety.register.field.area')" icon="checklist" tone="primary" cols="2">
        <x-input-field name="area" :label="__('safety.register.field.area')" required minlength="2" maxlength="180" :value="old('area', $assessment?->area)" />
        <x-input-field name="activity" :label="__('safety.register.field.activity')" maxlength="180" :value="old('activity', $assessment?->activity)" />
        <x-textarea-field name="description" :label="__('safety.register.field.description')" rows="3" maxlength="10000" span="2" :value="old('description', $assessment?->description)" />
        <x-input-field name="review_due_on" type="date" :label="__('safety.register.field.review_due_on')" :value="old('review_due_on', $assessment?->review_due_on?->toDateString())" />
    </x-form-group>
</x-modal>

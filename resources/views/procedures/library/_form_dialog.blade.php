{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Bibliotheksschritt anlegen oder bearbeiten (MVP-896). --}}
<x-modal :title="$step ? __('procedure.library.edit') : __('procedure.library.add')" :eyebrow="__('procedure.library.title')" icon="library_books" tone="primary"
         :action="$step ? route('procedures.library.update', $step) : route('procedures.library.store')" :method="$step ? 'PUT' : 'POST'"
         :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    <x-input-field name="code" :label="__('procedure.library.code')" :value="old('code', $step?->code)" required maxlength="60" />
    <x-select-field name="step_kind" :label="__('procedure.library.type')" required>
        @foreach (\App\Enums\Procedure\ProcedureStepType::cases() as $type)
            <option value="{{ $type->value }}" @selected(old('step_kind', $step?->step_kind?->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="label" :label="__('procedure.library.label')" :value="old('label', $step?->label)" required maxlength="180" />
    <x-textarea-field name="description" :label="__('procedure.library.description')" :value="old('description', $step?->description)" rows="2" />
    <x-checkbox-field name="is_required" :label="__('procedure.library.required')" :checked="(bool) old('is_required', $step?->is_required ?? true)" />
    <x-checkbox-field name="is_blocking" :label="__('procedure.library.blocking')" :checked="(bool) old('is_blocking', $step?->is_blocking ?? true)" />
    <x-checkbox-field name="requires_second_person" :label="__('procedure.library.second_person')" :checked="(bool) old('requires_second_person', $step?->requires_second_person ?? false)" />
    <x-select-field name="requires_proof_kind" :label="__('procedure.library.proof')">
        <option value="">—</option>
        @foreach (\App\Enums\Procedure\ProcedureProofType::cases() as $proof)
            <option value="{{ $proof->value }}" @selected(old('requires_proof_kind', $step?->requires_proof_kind?->value) === $proof->value)>{{ $proof->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="required_role" :label="__('procedure.library.role')" :value="old('required_role', $step?->required_role)" maxlength="40" />
    <x-input-field name="required_qualification_code" :label="__('procedure.library.qualification')" :value="old('required_qualification_code', $step?->required_qualification_code)" maxlength="60" />
</x-modal>

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

    {{-- Konfiguration je Schrittart (MVP-1569): gespeichert wird nur, was zur gewählten Schrittart gehört. --}}
    <input type="hidden" name="config_extra" value="{{ old('config_extra', $config['config_extra']) }}">
    <details class="rounded-box border border-base-300 p-3" @if (array_filter(array_diff_key($config, ['config_extra' => 1]))) open @endif>
        <summary class="cursor-pointer text-sm font-medium">{{ __('procedure.library.config') }}</summary>
        <p class="mt-2 text-xs text-muted">{{ __('procedure.library.config_hint') }}</p>
        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <x-input-field name="options" :label="__('procedure.field.options')" :hint="__('procedure.hint.options')" :value="old('options', $config['options'])" maxlength="2000" span="2" />
            <x-input-field name="unit" :label="__('procedure.field.unit')" :value="old('unit', $config['unit'])" maxlength="20" />
            <x-input-field name="wait_seconds" type="number" min="1" :label="__('procedure.field.waitSeconds')" :hint="__('procedure.hint.waitSeconds')" :value="old('wait_seconds', $config['wait_seconds'])" />
            <x-input-field name="min" type="number" step="any" :label="__('procedure.field.min')" :value="old('min', $config['min'])" />
            <x-input-field name="max" type="number" step="any" :label="__('procedure.field.max')" :value="old('max', $config['max'])" />
            <x-checkbox-field name="requires_prior_backup" :label="__('procedure.field.requiresPriorBackup')" :checked="(bool) old('requires_prior_backup', $config['requires_prior_backup'])" />
            <x-input-field name="prior_backup_max_age_minutes" type="number" min="1" :label="__('procedure.field.backupMaxAge')" :value="old('prior_backup_max_age_minutes', $config['prior_backup_max_age_minutes'])" />
            <x-select-field name="backup_scope" :label="__('procedure.backup.scope')">
                <option value="">{{ __('procedure.field.chosenAtRun') }}</option>
                @foreach (\App\Enums\Procedure\ProcedureBackupScope::cases() as $scope)
                    <option value="{{ $scope->value }}" @selected(old('backup_scope', $config['backup_scope']) === $scope->value)>{{ $scope->label() }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="storage_target" :label="__('procedure.backup.storage')">
                <option value="">{{ __('procedure.field.chosenAtRun') }}</option>
                @foreach (\App\Enums\Procedure\ProcedureBackupStorageTarget::cases() as $target)
                    <option value="{{ $target->value }}" @selected(old('storage_target', $config['storage_target']) === $target->value)>{{ $target->label() }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="verify_method" :label="__('procedure.backup.method')">
                <option value="">{{ __('procedure.field.chosenAtRun') }}</option>
                @foreach (\App\Enums\Procedure\ProcedureBackupVerifyMethod::cases() as $verify)
                    <option value="{{ $verify->value }}" @selected(old('verify_method', $config['verify_method']) === $verify->value)>{{ $verify->label() }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="min_size_kb" type="number" min="0" :label="__('procedure.field.backupMinSize')" :value="old('min_size_kb', $config['min_size_kb'])" />
            <x-input-field name="max_age_minutes" type="number" min="1" :label="__('procedure.field.backupMaxAge')" :value="old('max_age_minutes', $config['max_age_minutes'])" />
            <x-input-field name="second_person_role" :label="__('procedure.field.secondPersonRole')" :value="old('second_person_role', $config['second_person_role'])" maxlength="40" />
            <x-input-field name="second_person_min_qualification" :label="__('procedure.field.secondPersonQualification')" :value="old('second_person_min_qualification', $config['second_person_min_qualification'])" maxlength="60" />
            <x-checkbox-field name="second_person_allow_self" :label="__('procedure.field.secondPersonAllowSelf')" :checked="(bool) old('second_person_allow_self', $config['second_person_allow_self'])" />
            <x-input-field name="condition_step" :label="__('procedure.field.conditionStep')" :hint="__('procedure.hint.conditionStep')" :value="old('condition_step', $config['condition_step'])" maxlength="60" />
            <x-input-field name="condition_equals" :label="__('procedure.field.conditionEquals')" :hint="__('procedure.hint.conditionEquals')" :value="old('condition_equals', $config['condition_equals'])" maxlength="120" />
        </div>
    </details>
</x-modal>

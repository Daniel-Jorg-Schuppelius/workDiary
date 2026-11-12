{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _backup_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Backup-Nachweis eines Schritts erfassen (MVP-1566). Was die Vorlage festlegt ($fixed), steht nicht zur Wahl. --}}
@php
    /** @var \App\Models\Procedure\ProcedureStepRun $stepRun */
    $storage = $fixed['storage_target'] ?? null;
    $method = $fixed['verify_method'] ?? null;
    $limits = array_filter([
        (int) ($config['min_size_kb'] ?? 0) > 0 ? __('procedure.backup.limit_size', ['kb' => (int) $config['min_size_kb']]) : null,
        (int) ($config['max_age_minutes'] ?? 0) > 0 ? __('procedure.backup.limit_age', ['minutes' => (int) $config['max_age_minutes']]) : null,
    ]);
@endphp
<x-modal :title="__('procedure.backup.register')" :eyebrow="$stepRun->stepDef?->label" icon="backup" tone="primary"
         :action="route('procedure-runs.steps.backup.store', [$run, $stepRun])" method="POST"
         :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']" :submit-label="__('procedure.backup.register')">
    @if ($limits !== [])
        <p class="text-xs text-muted">{{ implode(' · ', $limits) }}</p>
    @endif
    <x-input-field name="source_label" :label="__('procedure.backup.source')" :hint="__('procedure.backup.source_hint')" :value="old('source_label')" required maxlength="180" />
    @if (isset($fixed['backup_scope']))
        <x-input-field name="backup_scope_label" :label="__('procedure.backup.scope')" :value="\App\Enums\Procedure\ProcedureBackupScope::from($fixed['backup_scope'])->label()" disabled />
    @else
        <x-select-field name="backup_scope" :label="__('procedure.backup.scope')" required>
            @foreach (\App\Enums\Procedure\ProcedureBackupScope::cases() as $scope)
                <option value="{{ $scope->value }}" @selected(old('backup_scope') === $scope->value)>{{ $scope->label() }}</option>
            @endforeach
        </x-select-field>
    @endif
    <x-input-field name="taken_at" type="datetime-local" :label="__('procedure.backup.taken_at')" :value="old('taken_at', now()->format('Y-m-d\TH:i'))" required />

    @if ($storage !== null)
        <x-input-field name="storage_target_label" :label="__('procedure.backup.storage')" :value="\App\Enums\Procedure\ProcedureBackupStorageTarget::from($storage)->label()" disabled />
    @else
        <x-select-field name="storage_target" :label="__('procedure.backup.storage')" :hint="__('procedure.backup.storage_hint')" required>
            @foreach (\App\Enums\Procedure\ProcedureBackupStorageTarget::cases() as $target)
                <option value="{{ $target->value }}" @selected(old('storage_target') === $target->value)>{{ $target->label() }}</option>
            @endforeach
        </x-select-field>
    @endif
    @if ($storage !== \App\Enums\Procedure\ProcedureBackupStorageTarget::External->value)
        <x-input-field name="proof" type="file" :label="__('procedure.backup.file')" :hint="__('procedure.backup.file_hint')" />
    @endif
    @if ($storage !== \App\Enums\Procedure\ProcedureBackupStorageTarget::Attachment->value)
        <x-input-field name="external_ref" :label="__('procedure.backup.external_ref')" :hint="__('procedure.backup.external_ref_hint')" :value="old('external_ref')" maxlength="255" />
        <x-input-field name="size_kb" type="number" min="0" :label="__('procedure.backup.size_kb')" :value="old('size_kb')" />
    @endif

    @if ($method !== null)
        <x-input-field name="verify_method_label" :label="__('procedure.backup.method')" :value="\App\Enums\Procedure\ProcedureBackupVerifyMethod::from($method)->label()" disabled />
    @else
        <x-select-field name="verify_method" :label="__('procedure.backup.method')" :hint="__('procedure.backup.method_hint')" required>
            @foreach (\App\Enums\Procedure\ProcedureBackupVerifyMethod::cases() as $verify)
                <option value="{{ $verify->value }}" @selected(old('verify_method') === $verify->value)>{{ $verify->label() }}</option>
            @endforeach
        </x-select-field>
    @endif
    @if ($method === null || $method === \App\Enums\Procedure\ProcedureBackupVerifyMethod::Checksum->value)
        <x-input-field name="checksum_value" :label="__('procedure.backup.checksum')" :hint="__('procedure.backup.checksum_hint')" :value="old('checksum_value')" maxlength="128" class="font-mono" />
    @endif
    <x-textarea-field name="verify_note" :label="__('procedure.backup.note')" :value="old('verify_note')" rows="2" maxlength="2000" />
</x-modal>

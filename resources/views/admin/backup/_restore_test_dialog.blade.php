{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _restore_test_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Restore-Test protokollieren (Feature 017, §6.3) --}}
@php
    /** @var \App\Models\RestoreTest $restoreTest */
    $today = \Illuminate\Support\Carbon::now()->format('Y-m-d');
@endphp
<x-modal
    :title="__('backup.title.log_restore_test')"
    icon="restore"
    tone="primary"
    :action="route('admin.backup.restore-tests.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('backup.action.save')"
>
    <x-form-group :legend="__('backup.section.restore_test')" icon="restore" tone="primary" cols="2">
        <x-input-field name="source"
                       :label="__('backup.field.source')"
                       type="text"
                       value="{{ old('source', 'nightly') }}"
                       required
                       maxlength="191"
                       placeholder="{{ __('backup.placeholder.source') }}" />

        <x-input-field name="tested_on"
                       :label="__('backup.field.tested_on')"
                       type="date"
                       value="{{ old('tested_on', $today) }}"
                       required
                       max="{{ $today }}" />

        <x-select-field name="result" :label="__('backup.field.result')" required>
            @foreach (\App\Enums\Backup\RestoreTestResult::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('result', \App\Enums\Backup\RestoreTestResult::Passed->value) === $case->value)>
                    {{ $case->label() }}
                </option>
            @endforeach
        </x-select-field>

        <x-input-field name="scope"
                       :label="__('backup.field.scope')"
                       type="text"
                       value="{{ old('scope') }}"
                       maxlength="191"
                       placeholder="{{ __('backup.placeholder.scope') }}" />

        <x-input-field name="restored_size_bytes"
                       :label="__('backup.field.restored_size_bytes')"
                       type="number"
                       value="{{ old('restored_size_bytes') }}"
                       min="0"
                       step="1" />

        <x-input-field name="duration_minutes"
                       :label="__('backup.field.duration_minutes')"
                       type="number"
                       value="{{ old('duration_minutes') }}"
                       min="0"
                       step="1" />

        <x-input-field name="next_due_on"
                       :label="__('backup.field.next_due')"
                       type="date"
                       value="{{ old('next_due_on') }}"
                       min="{{ $today }}" />

        <x-textarea-field span="2" name="notes"
                          :label="__('backup.field.notes')"
                          rows="3"
                          maxlength="5000"
                          placeholder="{{ __('backup.placeholder.notes') }}">{{ old('notes') }}</x-textarea-field>
    </x-form-group>
</x-modal>

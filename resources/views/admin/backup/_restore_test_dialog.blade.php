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
        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.source') }} *</label>
            <input type="text" name="source" required maxlength="191"
                   value="{{ old('source', 'nightly') }}"
                   class="input input-bordered w-full"
                   placeholder="{{ __('backup.placeholder.source') }}">
            @error('source')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.tested_on') }} *</label>
            <input type="date" name="tested_on" required max="{{ $today }}"
                   value="{{ old('tested_on', $today) }}"
                   class="input input-bordered w-full">
            @error('tested_on')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.result') }} *</label>
            <select name="result" required class="select select-bordered w-full">
                @foreach (\App\Enums\Backup\RestoreTestResult::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('result', \App\Enums\Backup\RestoreTestResult::Passed->value) === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
            @error('result')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.scope') }}</label>
            <input type="text" name="scope" maxlength="191"
                   value="{{ old('scope') }}"
                   class="input input-bordered w-full"
                   placeholder="{{ __('backup.placeholder.scope') }}">
            @error('scope')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.restored_size_bytes') }}</label>
            <input type="number" min="0" step="1" name="restored_size_bytes"
                   value="{{ old('restored_size_bytes') }}"
                   class="input input-bordered w-full">
            @error('restored_size_bytes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.duration_minutes') }}</label>
            <input type="number" min="0" step="1" name="duration_minutes"
                   value="{{ old('duration_minutes') }}"
                   class="input input-bordered w-full">
            @error('duration_minutes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('backup.field.next_due') }}</label>
            <input type="date" name="next_due_on" min="{{ $today }}"
                   value="{{ old('next_due_on') }}"
                   class="input input-bordered w-full">
            @error('next_due_on')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('backup.field.notes') }}</label>
            <textarea name="notes" rows="3" maxlength="5000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('backup.placeholder.notes') }}">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>

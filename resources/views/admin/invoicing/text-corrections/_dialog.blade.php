{{--
  Created on   : Mon Aug 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Wörterbuch-Eintrag anlegen/bearbeiten (modal-first). --}}
@php $isEdit = $correction !== null; @endphp
<x-modal
    :title="$isEdit ? __('textcorrections.action.edit') : __('textcorrections.action.new')"
    icon="spellcheck"
    tone="primary"
    :action="$isEdit ? route('admin.text-corrections.update', $correction) : route('admin.text-corrections.store')"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('textcorrections.action.submit')"
>
    <x-form-group :legend="__('textcorrections.legend')" icon="spellcheck" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="txc-wrong">{{ __('textcorrections.field.wrong') }}</label>
            <input id="txc-wrong" type="text" name="wrong" required maxlength="190"
                   value="{{ old('wrong', $correction?->wrong) }}" class="input input-bordered w-full font-mono"
                   placeholder="{{ __('textcorrections.wrong_placeholder') }}">
            <p class="text-xs text-base-content/60">{{ __('textcorrections.wrong_help') }}</p>
            @error('wrong')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="txc-correct">{{ __('textcorrections.field.correct') }}</label>
            <input id="txc-correct" type="text" name="correct" required maxlength="190"
                   value="{{ old('correct', $correction?->correct) }}" class="input input-bordered w-full font-mono"
                   placeholder="{{ __('textcorrections.correct_placeholder') }}">
            <p class="text-xs text-base-content/60">{{ __('textcorrections.correct_help') }}</p>
            @error('correct')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>

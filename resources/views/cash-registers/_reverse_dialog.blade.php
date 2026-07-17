{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reverse_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-414: Storno-Gegenbuchung. Variablen: $register, $entry --}}
<x-modal
    :title="__('Beleg #:seq stornieren', ['seq' => $entry->seq_no])"
    :eyebrow="$register->name"
    icon="undo"
    tone="warning"
    :action="route('cash-registers.entries.reverse', [$register, $entry])"
    method="POST"
    :submit-label="__('Storno buchen')">

    <div class="alert alert-warning text-sm">
        <span>{{ __('GoBD: Der Originaleintrag bleibt unverändert erhalten — es wird eine Gegenbuchung mit heutigem Datum erzeugt.') }}</span>
    </div>

    <x-form-group :legend="__('Begründung')" icon="description" tone="warning">
        <div class="fieldset">
            <label class="fieldset-label" for="cr-reason">{{ __('Begründung (Pflicht)') }} *</label>
            <textarea id="cr-reason" name="reason" rows="3" maxlength="400" required class="textarea textarea-bordered w-full">{{ old('reason') }}</textarea>
            @error('reason')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>

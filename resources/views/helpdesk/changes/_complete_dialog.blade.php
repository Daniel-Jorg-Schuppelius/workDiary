{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _complete_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Modal (Feature 065, MVP-157): Change abschließen — Outcome + PIR-Notizen.
  Emergency erzwingt das PIR (UI-Pflichtfeld, der Service als zweite
  Linie). Erwartet: $change, $outcomeLabels.
--}}
@php
    /** @var \App\Models\Change $change */
    $pirRequired = $change->change_type === 'emergency' && trim((string) $change->pir_notes) === '';
@endphp

<x-modal
    :title="__('Change abschließen')"
    :eyebrow="__('Service Desk')"
    icon="task_alt"
    tone="primary"
    size="md"
    :action="route('servicedesk.changes.complete', $change)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Abschließen')">

    <x-form-group :legend="__('Ergebnis')" icon="task_alt" tone="primary" cols="1">
        <x-select-field name="outcome" :label="__('Outcome')" required error="outcome">
            @foreach ($outcomeLabels as $val => $label)
                <option value="{{ $val }}" @selected(old('outcome') === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <div class="fieldset">
            <label class="fieldset-label" for="pir_notes">
                {{ __('PIR-Notizen') }}
                @if ($pirRequired)
                    <span class="text-muted">({{ __('Pflicht') }})</span>
                @endif
            </label>
            <textarea id="pir_notes" name="pir_notes" rows="4" maxlength="20000"
                      @if ($pirRequired) required @endif
                      class="textarea textarea-bordered w-full @error('pir_notes') textarea-error @enderror"
                      placeholder="{{ __('Ursache, Wirkung, Lessons Learned — bei Emergency Pflicht vor dem Abschluss.') }}">{{ old('pir_notes', $change->pir_notes) }}</textarea>
            @error('pir_notes')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>

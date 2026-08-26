{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _decision_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ausnutzbarkeits-Entscheidung (044-Kernregel): eine bewusste, begründete
  Festlegung, ob die Schwachstelle in der konkreten Konfiguration ausnutzbar
  ist. „Ausnutzbar"/„Nicht ausnutzbar" erfordern eine Pflichtbegründung.
  Variablen: $vulnerability (IsmsVulnerability)
--}}
<x-modal
    :title="__('isms.action.decide_exploitability')"
    :eyebrow="$vulnerability->displayNo()"
    icon="gavel"
    tone="primary"
    size="md"
    :action="route('isms.vulnerabilities.decide', $vulnerability)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.save')">

    <x-form-group :legend="__('isms.field.exploitability')" icon="gavel" tone="primary" cols="1">
        <p class="text-xs text-base-content/70">{{ __('isms.hint.exploitability_decision') }}</p>
        <x-select-field name="exploitability" :label="__('isms.field.exploitability')" required>
                @foreach (\App\Enums\Isms\Exploitability::cases() as $exp)
                    <option value="{{ $exp->value }}" @selected(old('exploitability', $vulnerability->exploitability->value) === $exp->value)>{{ $exp->label() }}</option>
                @endforeach
        </x-select-field>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.exploitability_note') }}</span>
            <textarea name="exploitability_note" rows="4" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.exploitability_note') }}">{{ old('exploitability_note', $vulnerability->exploitability_note) }}</textarea>
            <span class="text-xs text-muted">{{ __('isms.hint.exploitability_note_required') }}</span>
        </label>
    </x-form-group>
</x-modal>

{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sovereignty_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungshoheit ab Stichtag wechseln (Feature 125, MVP-671). Der Datenumzug
  selbst bleibt der Buchhaltungswechsel (Feature 110) — hier wird nur
  festgehalten, wer ab wann führt.
--}}
<x-modal
    :title="__('accounting.ledger.action.switch')"
    icon="swap_horiz"
    :action="route('finance.accounting.sovereignty')"
    method="POST"
    :submit-label="__('accounting.ledger.action.switch_submit')"
>
    <x-select-field name="sovereignty" :label="__('accounting.ledger.field.sovereignty')" :hint="__('accounting.ledger.hint.sovereignty_switch')">
        @foreach ($sovereigntyOptions as $option)
            <option value="{{ $option->value }}" @selected(old('sovereignty') === $option->value)>{{ $option->label() }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="external_provider" type="text" maxlength="64"
                   :label="__('accounting.ledger.field.external_provider')"
                   :hint="__('accounting.ledger.hint.external_provider')"
                   :value="old('external_provider', $profile->external_provider ?? '')" />

    <x-input-field name="valid_from" type="date" required
                   :label="__('accounting.ledger.field.valid_from')"
                   :value="old('valid_from', $suggestedFrom)" />

    <x-input-field name="reason" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.reason')"
                   :value="old('reason', '')" />
</x-modal>

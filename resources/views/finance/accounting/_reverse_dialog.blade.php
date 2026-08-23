{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reverse_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Storno (Feature 125, MVP-672): erzeugt eine echte Gegenbuchung. Die
  Begründung ist Pflicht — sie ist der einzige Teil der Korrektur, den die
  Zahlen selbst nicht erzählen.
--}}
<x-modal
    :title="__('accounting.ledger.action.reverse')"
    :eyebrow="'#' . ($entry->journal_no ?? '—')"
    icon="undo"
    :action="route('finance.accounting.journal.reverse', $entry)"
    method="POST"
    :submit-label="__('accounting.ledger.action.reverse_submit')"
>
    <p class="text-sm text-base-content/70">{{ __('accounting.ledger.reverse_hint') }}</p>

    <x-input-field name="reversal_reason" type="text" required maxlength="500"
                   :label="__('accounting.ledger.field.reversal_reason')"
                   :value="old('reversal_reason', '')" />

    <x-input-field name="booked_on" type="date"
                   :label="__('accounting.ledger.field.reversal_booked_on')"
                   :hint="__('accounting.ledger.hint.reversal_booked_on')"
                   :value="old('booked_on', '')" />
</x-modal>

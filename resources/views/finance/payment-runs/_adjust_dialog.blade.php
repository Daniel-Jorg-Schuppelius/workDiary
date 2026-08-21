{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _adjust_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kürzung/Teilzahlung einer Position (Feature 120, MVP-609). Ein Abzug ohne
  Grund wäre später nicht mehr erklärbar — deshalb ist er Pflicht.
--}}
<x-modal
    :title="__('sepa.action.adjust')"
    :eyebrow="$item->party_name"
    icon="edit"
    :action="route('finance.payment-runs.items.adjust', [$run, $item])"
    method="POST"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">
        {{ __('sepa.adjust_hint', ['gross' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($item->gross_amount ?? $item->amount), 2, withThousandsSeparator: true)]) }}
    </p>

    <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                   :label="__('sepa.column.amount')"
                   :value="old('amount', (string) $item->amount)" />

    <x-input-field name="deduction_reason" type="text" maxlength="191"
                   :label="__('sepa.column.deduction')"
                   :value="old('deduction_reason', (string) $item->deduction_reason)" />
</x-modal>

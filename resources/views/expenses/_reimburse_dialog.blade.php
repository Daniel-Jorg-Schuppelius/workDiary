{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reimburse_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $expense --}}
<x-modal
    :title="__('Als erstattet markieren')"
    :eyebrow="__('Spesen-Genehmigung')"
    icon="payments"
    tone="info"
    :action="route('expense-approvals.reimburse', $expense)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Markieren')">

    <div role="status" class="alert alert-info text-sm">
        <div>
            <div class="font-semibold">{{ $expense->user?->name }}</div>
            <div class="text-base-content/70">
                {{ $expense->date->fdate() }} ·
                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($expense->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $expense->currency->value }}
            </div>
            @if ($expense->description)
                <div class="mt-1">{{ $expense->description }}</div>
            @endif
        </div>
    </div>

    <x-input-field name="reimbursement_reference" class="mt-3" maxlength="120"
                   :label="__('Erstattungsreferenz')"
                   :placeholder="__('z. B. Verwendungszweck der Überweisung')"
                   :value="old('reimbursement_reference')" />
</x-modal>

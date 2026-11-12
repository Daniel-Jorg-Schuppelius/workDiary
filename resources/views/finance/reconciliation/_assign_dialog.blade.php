{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Freie Belegsuche beim Einzelumsatz (Feature 045, MVP-1557): jede offene
  Rechnung, freigegebene Spese oder jedes Kundenkonto als Ziel — über die
  höchstens fünf Vorschläge hinaus, mit Teil- und Überzahlung. Bestätigt über
  den vorhandenen confirm-Pfad.
--}}
<x-modal
    :title="__('bank.assign.title')"
    icon="search"
    :action="route('finance.reconciliation.confirm', $transaction->sqid)"
    method="POST"
    :submit-label="__('bank.action.confirm_selected')"
>
    <p class="text-sm text-base-content/70">
        {{ $transaction->booking_date->fdate() }} ·
        {{ $transaction->isCredit() ? '+' : '−' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transaction->amount, 2, withThousandsSeparator: true) }}
        {{ $transaction->currency->value }}
        @if ($transaction->counterparty_name)
            · {{ $transaction->counterparty_name }}
        @endif
        @if ($transaction->purpose)
            · {{ \Illuminate\Support\Str::limit($transaction->purpose, 90) }}
        @endif
    </p>
    <p class="text-xs text-muted">{{ __('bank.assign.hint') }}</p>

    <x-select-field name="allocations[0][target]" id="assign-target" error="allocations.0.target" required :label="__('bank.assign.target')">
        <option value="">{{ __('bank.split.target_placeholder') }}</option>
        @foreach ($targets as $option)
            <option value="{{ $option['value'] }}" @selected(old('allocations.0.target') === $option['value'])>{{ $option['label'] }}</option>
        @endforeach
    </x-select-field>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="allocations[0][amount]" id="assign-amount" error="allocations.0.amount" type="number" step="0.01" min="0.01" required
                       :label="__('bank.field.amount')"
                       :value="old('allocations.0.amount', number_format(abs((float) $transaction->amount), 2, '.', ''))" />

        <x-select-field name="allocations[0][kind]" id="assign-kind" error="allocations.0.kind" :label="__('bank.assign.kind')">
            <option value="">{{ __('bank.assign.kind_auto') }}</option>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('allocations.0.kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-input-field name="allocations[0][note]" id="assign-note" error="allocations.0.note" type="text" maxlength="500"
                   :label="__('bank.assign.note')"
                   :value="old('allocations.0.note', '')" />
</x-modal>

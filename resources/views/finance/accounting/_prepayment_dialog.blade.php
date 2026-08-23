{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _prepayment_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sondervorauszahlung buchen (Feature 125, MVP-685). Der Rechenweg steht offen
  daneben — 1/11 der Vorauszahlungen des Vorjahres, bei unterjährigem Beginn
  hochgerechnet (§ 47 UStDV).
--}}
<x-modal
    :title="__('accounting.filing.prepayment.title')"
    icon="payments"
    :action="route('finance.accounting.prepayment')"
    method="POST"
    :submit-label="__('accounting.filing.prepayment.submit')"
>
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="info" />
        <div>
            <div class="font-medium">
                {{ __('accounting.filing.prepayment.calculation', [
                    'year' => $calculation['prior_year'],
                    'tax' => $calculation['prior_year_tax'],
                    'annualised' => $calculation['annualised'],
                    'amount' => $calculation['amount'],
                ]) }}
            </div>
            @if ($calculation['months_active'] < 12)
                <p class="text-xs">{{ __('accounting.filing.prepayment.annualised_hint', ['months' => $calculation['months_active']]) }}</p>
            @endif
            <p class="text-xs">{{ __('accounting.filing.prepayment.due_hint', ['date' => \Carbon\Carbon::parse($calculation['due_on'])->fdate()]) }}</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="year" type="number" min="2000" max="2100" required
                       :label="__('accounting.filing.field.year')"
                       :value="old('year', $calculation['year'])" />
        <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                       :label="__('accounting.filing.field.special_prepayment')"
                       :value="old('amount', $calculation['amount'])" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="prepayment_account" :label="__('accounting.filing.field.prepayment_account')"
                        :hint="__('accounting.filing.hint.prepayment_account')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(in_array($account->number, ['1781', '3830'], true))>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="money_account" :label="__('accounting.filing.field.money_account')">
            @foreach ($moneyAccounts as $account)
                <option value="{{ $account->sqid }}">{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-input-field name="booked_on" type="date" required
                   :label="__('accounting.ledger.column.booked_on')"
                   :value="old('booked_on', $calculation['due_on'])" />
</x-modal>

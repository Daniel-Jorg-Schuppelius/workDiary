{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bank_direct_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bankumsatz ohne Beleg direkt aufs Sachkonto buchen (Feature 125, MVP-1557):
  Bankgebühr, Zinsen, Privat/Verrechnung — neben Klärungskonto und interner
  Umbuchung. Der Umsatz ist der Bruttobetrag.
--}}
<x-modal
    :title="__('accounting.bank_direct.title')"
    icon="account_balance"
    :action="route('finance.accounting.inbox.bank-direct.store', $transaction->sqid)"
    method="POST"
    :submit-label="__('accounting.bank_direct.action.post_submit')"
>
    <p class="text-sm text-base-content/70">
        {{ $transaction->booking_date->fdate() }} ·
        {{ $transaction->isCredit() ? '+' : '−' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transaction->amount, 2, withThousandsSeparator: true) }}
        {{ $transaction->currency->value }}
        @if ($transaction->purpose)
            · {{ \Illuminate\Support\Str::limit($transaction->purpose, 90) }}
        @endif
    </p>
    <p class="text-xs text-muted">{{ __('accounting.bank_direct.hint') }}</p>

    <x-select-field name="account" required :label="__('accounting.bank_direct.field.account')">
        @foreach ($accounts as $account)
            <option value="{{ $account->sqid }}" @selected(old('account') === $account->sqid)>{{ $account->displayLabel() }}</option>
        @endforeach
    </x-select-field>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="tax_code" :label="__('accounting.bank_direct.field.tax_code')" :hint="__('accounting.bank_direct.hint_field.tax_code')">
            <option value="">—</option>
            @foreach ($taxCodes as $taxCode)
                <option value="{{ $taxCode->sqid }}" @selected(old('tax_code') === $taxCode->sqid)>{{ $taxCode->code }} · {{ $taxCode->rate }} %</option>
            @endforeach
        </x-select-field>

        @if ($costCenters->isNotEmpty())
            <x-select-field name="cost_center" :label="__('accounting.ledger.field.cost_center')">
                <option value="">—</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->sqid }}" @selected(old('cost_center') === $costCenter->sqid)>{{ $costCenter->code }} · {{ $costCenter->label }}</option>
                @endforeach
            </x-select-field>
        @endif
    </div>

    <x-input-field name="note" type="text" required minlength="3" maxlength="150"
                   :label="__('accounting.bank_direct.field.note')"
                   :hint="__('accounting.bank_direct.hint_field.note')"
                   :value="old('note', \Illuminate\Support\Str::limit((string) $transaction->purpose, 140, ''))" />

    <x-accounting.four-eyes-note />
</x-modal>

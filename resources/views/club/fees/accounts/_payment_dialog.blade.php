{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _payment_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zahlung erfassen (in #entry-modal geladen). Variablen: $account, $claim|null, $claims (offen), $methods, $today --}}
<x-modal
    :title="__('club.fees.action.record_payment')"
    :eyebrow="$account->name"
    icon="payments"
    tone="primary"
    :action="route('club.fees.payments.store', $account)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.payments')" icon="payments" tone="primary" cols="2" :description="__('club.fees.hint.payment')">
        <x-input-field name="amount" inputmode="decimal" :label="__('club.fees.field.amount')" required maxlength="20" :value="old('amount', $claim?->openAmount()->getAmount())" />
        <x-input-field name="paid_on" type="date" :label="__('club.fees.field.paid_on')" required :value="old('paid_on', $today->toDateString())" />
        <x-select-field name="method" :label="__('club.fees.field.method')" required>
            @foreach ($methods as $method)
                <option value="{{ $method->value }}" @selected(old('method', 'transfer') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="claim_id" :label="__('club.fees.field.claim')" :hint="__('club.fees.hint.payment_claim')">
            <option value="">{{ __('club.fees.label.claim_auto') }}</option>
            @foreach ($claims as $open)
                <option value="{{ $open->sqid }}" @selected(old('claim_id', $claim?->sqid) === $open->sqid)>{{ $open->number }} · {{ $open->openAmount()->format() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="reference" :label="__('club.fees.field.reference')" maxlength="140" :value="old('reference')" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>

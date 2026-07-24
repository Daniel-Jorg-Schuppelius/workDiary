{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _payment_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Erwartet: $customer. Manuelle Zahlung aufs Kundenkonto (Feature 098);
     negativer Betrag = Korrektur/Erstattung. --}}

<x-modal
    :title="__('customer-billing.book_payment')"
    :eyebrow="$customer->name"
    icon="payments"
    tone="primary"
    :action="route('customers.billing.payments.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer-billing.book_payment')">

    <x-form-group :legend="__('customer-billing.payment')" icon="payments" tone="primary" cols="2">
        <x-input-field name="paid_on" type="date" :label="__('customer-billing.paid_on')" required
                       :value="old('paid_on', now(\App\Support\Tz::current())->toDateString())" />
        <x-input-field name="amount" type="number" step="0.01" :label="__('customer-billing.amount')" required
                       :hint="__('customer-billing.amount_hint')"
                       :value="old('amount')" />
        <x-input-field name="note" span="2" :label="__('Notiz')" maxlength="500" :value="old('note')" />
    </x-form-group>
</x-modal>

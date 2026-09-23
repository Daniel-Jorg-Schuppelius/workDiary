{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _account_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragskonto anlegen/bearbeiten (in #entry-modal geladen). Variablen: $account|null, $customers (frei) --}}
<x-modal
    :title="$account ? __('club.action.edit') : __('club.fees.action.create_account')"
    :eyebrow="__('club.fees.title.accounts')"
    icon="account_balance_wallet"
    tone="primary"
    :action="$account ? route('club.fees.accounts.update', $account) : route('club.fees.accounts.store')"
    :method="$account ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.payer')" icon="account_balance_wallet" tone="primary" cols="2" :description="__('club.fees.hint.account')">
        @unless ($account)
            <x-select-field name="customer_id" :label="__('club.fees.field.existing_customer')" span="2" :hint="__('club.fees.hint.existing_customer')">
                <option value="">{{ __('club.fees.label.new_customer') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected((string) old('customer_id') === $customer->sqid)>{{ $customer->name }}@if ($customer->number) · {{ $customer->number }}@endif</option>
                @endforeach
            </x-select-field>
        @endunless
        <x-input-field name="name" :label="__('club.fees.field.name')" maxlength="160" :required="$account !== null" :value="old('name', $account?->name)" />
        <x-input-field name="email" type="email" :label="__('club.field.email')" maxlength="255" :value="old('email', $account?->email)" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $account?->notes)" />
    </x-form-group>
</x-modal>

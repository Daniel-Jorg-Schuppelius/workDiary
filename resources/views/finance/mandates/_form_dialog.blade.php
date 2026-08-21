{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mandat erfassen (Feature 120, MVP-609).
--}}
<x-modal
    :title="__('sepa.mandate.action.create')"
    icon="assignment"
    :action="route('finance.mandates.store')"
    method="POST"
    :submit-label="__('Speichern')"
>
    <x-select-field name="customer_id" :label="__('sepa.mandate.column.customer')" required>
        @foreach ($customers as $customer)
            <option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
        @endforeach
    </x-select-field>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="reference" type="text" maxlength="35" required
                       :label="__('sepa.mandate.column.reference')"
                       :value="old('reference', '')"
                       :hint="__('sepa.mandate.reference_hint')" />
        <x-select-field name="kind" :label="__('sepa.mandate.column.kind')" required>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="signed_on" type="date" required
                       :label="__('sepa.mandate.column.signed_on')"
                       :value="old('signed_on', now()->toDateString())" />
        <x-input-field name="account_holder" type="text" maxlength="191"
                       :label="__('sepa.mandate.column.account_holder')"
                       :value="old('account_holder', '')" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="iban" type="text" maxlength="40" required
                       :label="__('sepa.mandate.column.iban')"
                       :value="old('iban', '')" />
        <x-input-field name="bic" type="text" maxlength="20"
                       :label="__('sepa.mandate.column.bic')"
                       :value="old('bic', '')" />
    </div>

    <x-input-field name="note" type="text" maxlength="191"
                   :label="__('sepa.mandate.column.note')"
                   :value="old('note', '')" />
</x-modal>

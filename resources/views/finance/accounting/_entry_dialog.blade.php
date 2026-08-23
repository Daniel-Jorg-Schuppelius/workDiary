{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Handbuchung (Feature 125, MVP-672): Soll an Haben in einem Betrag. Mehr
  Zeilen entstehen ab MVP-673 aus den Quellenadaptern — eine frei bebaubare
  Zeilenmaske ohne Adapter wäre eine Einladung zur Fehlbuchung.
--}}
<x-modal
    :title="__('accounting.ledger.action.add_entry')"
    icon="post_add"
    :action="route('finance.accounting.journal.store')"
    method="POST"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="booked_on" type="date" required
                       :label="__('accounting.ledger.column.booked_on')"
                       :value="old('booked_on', now()->toDateString())" />
        <x-input-field name="document_on" type="date"
                       :label="__('accounting.ledger.column.document_on')"
                       :value="old('document_on', '')" />
    </div>

    <x-input-field name="memo" type="text" required maxlength="191"
                   :label="__('accounting.ledger.column.memo')"
                   :value="old('memo', '')" />

    <x-input-field name="document_reference" type="text" maxlength="64"
                   :label="__('accounting.ledger.column.document_reference')"
                   :value="old('document_reference', '')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="debit_account" :label="__('accounting.ledger.column.debit')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('debit_account') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="credit_account" :label="__('accounting.ledger.column.credit')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('credit_account') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                   :label="__('accounting.ledger.column.amount')"
                   :value="old('amount', '')" />

    <x-checkbox-field name="post" :label="__('accounting.ledger.field.post_now')"
                      :hint="__('accounting.ledger.hint.post_now')"
                      :checked="(bool) old('post', false)" />
</x-modal>

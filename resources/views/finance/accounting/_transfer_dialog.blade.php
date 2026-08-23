{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transfer_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Interne Umbuchung zwischen Geldkonten (Feature 125, MVP-681). Bankabhebung
  und Kasseneinzahlung sind ein Geldfluss — der Vorgang erzeugt genau eine
  Buchung, damit der Betrag nicht zweimal im Ergebnis landet.
--}}
<x-modal
    :title="__('accounting.transfer.action.record')"
    icon="swap_horiz"
    :action="route('finance.accounting.inbox.transfer.store')"
    method="POST"
    :submit-label="__('accounting.transfer.action.record_submit')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="from_account" :label="__('accounting.transfer.field.from_account')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}">{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="to_account" :label="__('accounting.transfer.field.to_account')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}">{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                       :label="__('accounting.ledger.column.amount')" />
        <x-input-field name="booked_on" type="date" required
                       :label="__('accounting.ledger.column.booked_on')"
                       :value="old('booked_on', now()->toDateString())" />
    </div>

    <x-input-field name="note" type="text" required minlength="3" maxlength="500"
                   :label="__('accounting.ledger.field.note')"
                   :hint="__('accounting.transfer.hint.note')" />
</x-modal>

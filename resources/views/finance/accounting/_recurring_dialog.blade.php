{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recurring_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wiederkehrende Vorlage (Feature 125, MVP-675). Die Kontenfelder gelten nur
  für Buchungsvorlagen — eine Belegerwartung bucht nichts, sie wartet.
--}}
<x-modal
    :title="$template ? __('accounting.recurring.action.edit') : __('accounting.recurring.action.add')"
    icon="event_repeat"
    :action="$template ? route('finance.accounting.recurring.update', $template) : route('finance.accounting.recurring.store')"
    :method="$template ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    <x-select-field name="kind" :label="__('accounting.recurring.column.kind')" :hint="__('accounting.recurring.hint.kind')">
        @foreach ($kinds as $kind)
            <option value="{{ $kind->value }}" @selected(old('kind', $template->kind->value ?? '') === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="name" type="text" required maxlength="191"
                   :label="__('accounting.recurring.column.name')"
                   :value="old('name', $template->name ?? '')" />

    <div class="grid gap-3 sm:grid-cols-3">
        <x-select-field name="interval" :label="__('accounting.recurring.column.interval')">
            @foreach ($intervals as $interval)
                <option value="{{ $interval->value }}" @selected(old('interval', $template->interval->value ?? '') === $interval->value)>{{ $interval->label() }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="due_day" type="number" min="1" max="28" required
                       :label="__('accounting.recurring.field.due_day')"
                       :hint="__('accounting.recurring.hint.due_day')"
                       :value="old('due_day', (string) ($template->due_day ?? 1))" />

        <x-input-field name="expected_amount" type="number" step="0.01" min="0"
                       :label="__('accounting.recurring.column.expected')"
                       :value="old('expected_amount', $template?->expected_amount?->getAmount() ?? '')" />
    </div>

    <x-date-range layout="split" from-name="starts_on" to-name="ends_on"
                  :from-label="__('accounting.recurring.field.starts_on')"
                  :to-label="__('accounting.recurring.field.ends_on')"
                  :from="old('starts_on', $template?->starts_on?->toDateString() ?? now()->toDateString())"
                  :to="old('ends_on', $template?->ends_on?->toDateString() ?? '')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="debit_account" :label="__('accounting.ledger.column.debit')" :hint="__('accounting.recurring.hint.accounts')">
            <option value="">{{ __('accounting.recurring.no_account') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('debit_account') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="credit_account" :label="__('accounting.ledger.column.credit')">
            <option value="">{{ __('accounting.recurring.no_account') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('credit_account') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-input-field name="note" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('note', $template->note ?? '')" />

    @if ($preview !== [])
        <p class="text-xs text-muted">
            {{ __('accounting.recurring.preview', ['dates' => implode(', ', $preview)]) }}
        </p>
    @endif
</x-modal>

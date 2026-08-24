{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rule_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsregel anlegen/ändern (Feature 125, MVP-673). Ein neuer Stichtag
  erzeugt eine Folgefassung statt die bestehende Regel zu überschreiben.
--}}
<x-modal
    :title="$rule ? __('accounting.rules.action.edit') : __('accounting.rules.action.add')"
    icon="rule"
    :action="$rule ? route('finance.accounting.rules.update', $rule) : route('finance.accounting.rules.store')"
    :method="$rule ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="source_kind" :label="__('accounting.inbox.column.kind')">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('source_kind', $rule->source_kind->value ?? '') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="role" :label="__('accounting.rules.column.role')" :hint="__('accounting.rules.hint.role')">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $rule->role->value ?? '') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-select-field name="account" :label="__('accounting.ledger.column.account')">
        @foreach ($accounts as $account)
            <option value="{{ $account->sqid }}" @selected(old('account', $rule?->account?->sqid ?? '') === $account->sqid)>{{ $account->displayLabel() }}</option>
        @endforeach
    </x-select-field>

    <x-select-field name="tax_code" :label="__('accounting.rules.field.tax_code')" :hint="__('accounting.rules.hint.tax_code')">
        <option value="">{{ __('accounting.rules.no_tax_code') }}</option>
        @foreach ($taxCodes as $code)
            <option value="{{ $code->sqid }}" @selected(old('tax_code', $rule?->taxCode?->sqid ?? '') === $code->sqid)>{{ $code->code }} — {{ $code->name }}</option>
        @endforeach
    </x-select-field>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="match_key" type="text" maxlength="64"
                       :label="__('accounting.rules.field.match_key')"
                       :hint="__('accounting.rules.hint.match')"
                       :value="old('match_key', $rule?->match_criteria ? array_key_first($rule->match_criteria) : '')" />
        <x-input-field name="match_value" type="text" maxlength="64"
                       :label="__('accounting.rules.field.match_value')"
                       :value="old('match_value', $rule?->match_criteria ? (string) reset($rule->match_criteria) : '')" />
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="priority" type="number" min="1" max="999" required
                       :label="__('accounting.rules.column.priority')"
                       :hint="__('accounting.rules.hint.priority')"
                       :value="old('priority', (string) ($rule->priority ?? 100))" />
        <x-date-range layout="split" grid-class="contents" form-control size="" type="date"
                      from-name="valid_from" to-name="valid_to"
                      from-id="valid_from" to-id="valid_to" from-required
                      :from-label="__('accounting.ledger.column.from')"
                      :to-label="__('accounting.ledger.column.to')"
                      :from="old('valid_from', $rule?->valid_from?->toDateString() ?? now()->toDateString())"
                      :to="old('valid_to', $rule?->valid_to?->toDateString() ?? '')"
                      :from-error="$errors->first('valid_from') ?: null"
                      :to-error="$errors->first('valid_to') ?: null" />
    </div>

    <x-input-field name="note" type="text" maxlength="191"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('note', $rule->note ?? '')" />
</x-modal>

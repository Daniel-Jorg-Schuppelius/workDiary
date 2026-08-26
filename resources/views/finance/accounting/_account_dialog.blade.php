{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _account_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Konto anlegen/bearbeiten (Feature 125, MVP-672/680).
--}}
<x-modal
    :title="$account ? __('accounting.ledger.action.edit_account') : __('accounting.ledger.action.add_account')"
    icon="account_tree"
    :action="$account ? route('finance.accounting.accounts.update', $account) : route('finance.accounting.accounts.store')"
    :method="$account ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="number" type="text" required maxlength="16"
                       :label="__('accounting.ledger.column.number')"
                       :value="old('number', $account->number ?? '')" />
        <x-input-field name="datev_account" type="text" maxlength="16"
                       :label="__('accounting.ledger.field.datev_account')"
                       :hint="__('accounting.ledger.hint.datev_account')"
                       :value="old('datev_account', $account->datev_account ?? '')" />
    </div>

    <x-input-field name="name" type="text" required maxlength="191"
                   :label="__('accounting.ledger.column.name')"
                   :value="old('name', $account->name ?? '')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="type" :label="__('accounting.ledger.column.type')">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $account->type->value ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="normal_balance" :label="__('accounting.ledger.column.normal_balance')"
                        :hint="__('accounting.ledger.hint.normal_balance')">
            @foreach ($sides as $side)
                <option value="{{ $side->value }}" @selected(old('normal_balance', $account->normal_balance->value ?? '') === $side->value)>{{ $side->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
        <x-checkbox-field name="is_open_item" :label="__('accounting.ledger.flag.open_item')"
                          :checked="(bool) old('is_open_item', $account->is_open_item ?? false)" />
        <x-checkbox-field name="is_bank" :label="__('accounting.ledger.flag.bank')"
                          :checked="(bool) old('is_bank', $account->is_bank ?? false)" />
        <x-checkbox-field name="is_cash" :label="__('accounting.ledger.flag.cash')"
                          :checked="(bool) old('is_cash', $account->is_cash ?? false)" />
        <x-checkbox-field name="is_clearing" :label="__('accounting.ledger.flag.clearing')"
                          :checked="(bool) old('is_clearing', $account->is_clearing ?? false)" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="euer_category" :label="__('accounting.ledger.field.euer_category')"
                        :hint="__('accounting.ledger.hint.euer_category')">
            <option value="">{{ __('accounting.ledger.field.euer_category_none') }}</option>
            @foreach ($euerCategories as $category)
                <option value="{{ $category->value }}" @selected(old('euer_category', $account->euer_category?->value ?? '') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="deductible_percent" type="number" step="0.01" min="0" max="100"
                       :label="__('accounting.ledger.field.deductible_percent')"
                       :hint="__('accounting.ledger.hint.deductible_percent')"
                       :value="old('deductible_percent', $account->deductible_percent ?? '100.00')" />

        {{-- BWA-Zeile (Feature 142): ausdrückliche Zuordnung schlägt den SKR-Nummernkreis. --}}
        <x-select-field name="bwa_group" :label="__('accounting.ledger.field.bwa_group')"
                        :hint="__('accounting.ledger.hint.bwa_group')" span="2">
            <option value="">{{ __('accounting.ledger.field.bwa_group_none') }}</option>
            @foreach ($bwaGroups as $group)
                <option value="{{ $group->value }}" @selected(old('bwa_group', $account->bwa_group?->value ?? '') === $group->value)>{{ $group->label() }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-input-field name="description" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.description')"
                   :value="old('description', $account->description ?? '')" />
</x-modal>

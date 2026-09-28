{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fixed_asset_class_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlagenklasse anlegen/bearbeiten (MVP-999). Variablen: $assetClass (FixedAssetClass|null), $methods, $accounts
--}}
<x-modal
    :title="$assetClass !== null ? __('accounting.fixed_assets.classes.action.edit') : __('accounting.fixed_assets.classes.action.add')"
    :eyebrow="__('accounting.fixed_assets.classes.title')"
    icon="category" tone="primary"
    :action="$assetClass !== null ? route('finance.accounting.fixed-asset-classes.update', $assetClass) : route('finance.accounting.fixed-asset-classes.store')"
    :method="$assetClass !== null ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-form-group :legend="__('accounting.fixed_assets.classes.title')" icon="category" tone="primary" cols="2">
        <x-input-field name="name" :label="__('accounting.fixed_assets.classes.field.name')" required minlength="2" maxlength="120" span="2"
                       :value="old('name', $assetClass?->name)" />
        <x-input-field name="useful_life_months" type="number" min="1" max="1200" required
                       :label="__('accounting.fixed_assets.column.useful_life')" :hint="__('accounting.fixed_assets.hint.useful_life')"
                       :value="old('useful_life_months', $assetClass?->useful_life_months)" />
        <x-select-field name="depreciation_method" :label="__('accounting.fixed_assets.field.method')">
            @foreach ($methods as $method)
                <option value="{{ $method->value }}" @selected(old('depreciation_method', $assetClass?->depreciation_method?->value ?? 'linear') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="asset_account" :label="__('accounting.fixed_assets.field.asset_account')">
            <option value="">{{ __('accounting.fixed_assets.account_from_rule') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('asset_account', $assetClass?->assetAccount?->sqid ?? '') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="depreciation_account" :label="__('accounting.fixed_assets.field.depreciation_account')">
            <option value="">{{ __('accounting.fixed_assets.account_from_rule') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('depreciation_account', $assetClass?->depreciationAccount?->sqid ?? '') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="is_active" span="2" :label="__('accounting.fixed_assets.classes.field.is_active')"
                          :hint="__('accounting.fixed_assets.classes.hint.is_active')" :checked="old('is_active', $assetClass?->is_active ?? true)" />
        <x-textarea-field name="note" :label="__('accounting.ledger.field.note')" rows="2" maxlength="2000" span="2"
                          :value="old('note', $assetClass?->note)" />
    </x-form-group>
</x-modal>

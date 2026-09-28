{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fixed_asset_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage anlegen/bearbeiten (Feature 133, MVP-698). Nach der ersten
  festgeschriebenen AfA sind die wertbestimmenden Felder gesperrt.
  Variablen: $fixedAsset (FixedAsset|null), $frozen, $methods, $accounts, $devices,
  $classes (Anlagenklassen, MVP-999), $prefill (Vorbelegung aus Eingangsrechnung/Auslage)
--}}
@php
    $isEdit = $fixedAsset !== null;
    $prefill ??= null;
    $classes ??= collect();
    // Beim Anlegen dürfen Nutzungsdauer und Methode aus der Anlagenklasse kommen.
    $classDefaults = ! $isEdit && $classes->isNotEmpty();
@endphp

<x-modal
    :title="$isEdit ? __('accounting.fixed_assets.action.edit') : __('accounting.fixed_assets.action.add')"
    :eyebrow="__('accounting.fixed_assets.title')"
    icon="precision_manufacturing"
    tone="primary"
    :action="$isEdit ? route('finance.accounting.fixed-assets.update', $fixedAsset) : route('finance.accounting.fixed-assets.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @if ($frozen)
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="lock" />
            <span>{{ __('accounting.fixed_assets.hint.frozen') }}</span>
        </div>
    @endif

    @if ($prefill !== null)
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="receipt_long" />
            <span>{{ $prefill['label'] }}</span>
        </div>
        <input type="hidden" name="source_kind" value="{{ $prefill['source_kind'] }}">
        <input type="hidden" name="source_ref" value="{{ $prefill['source_ref'] }}">
    @endif

    <x-form-group :legend="__('accounting.fixed_assets.section.master')" icon="precision_manufacturing" tone="primary" cols="2">
        <x-input-field name="name" :label="__('accounting.fixed_assets.column.name')" required minlength="2" maxlength="180"
                       :value="old('name', $fixedAsset?->name ?? $prefill['name'] ?? null)" span="2" />

        @if ($classes->isNotEmpty())
            <x-select-field name="fixed_asset_class" :label="__('accounting.fixed_assets.field.class')" :hint="__('accounting.fixed_assets.hint.class')" span="2">
                <option value="">—</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->sqid }}" @selected(old('fixed_asset_class', $fixedAsset?->assetClass?->sqid ?? '') === $class->sqid)>{{ $class->name }}</option>
                @endforeach
            </x-select-field>
        @endif

        <x-select-field name="device" :label="__('accounting.fixed_assets.field.device')" :hint="__('accounting.fixed_assets.hint.device')" span="2">
            <option value="">—</option>
            @foreach ($devices as $device)
                <option value="{{ $device->sqid }}" @selected(old('device', $fixedAsset?->asset?->sqid ?? '') === $device->sqid)>{{ $device->asset_no }} · {{ $device->name }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="acquired_on" type="date" required
                       :label="__('accounting.fixed_assets.column.acquired_on')"
                       :value="old('acquired_on', $fixedAsset?->acquired_on?->toDateString() ?? $prefill['acquired_on'] ?? now()->toDateString())"
                       :readonly="$frozen" />
        <x-input-field name="acquisition_cost" type="number" step="0.01" min="0.01" required
                       :label="__('accounting.fixed_assets.column.cost')"
                       :value="old('acquisition_cost', $fixedAsset?->acquisition_cost?->getAmount() ?? $prefill['acquisition_cost'] ?? null)"
                       :readonly="$frozen" />
        <x-input-field name="residual_value" type="number" step="0.01" min="0"
                       :label="__('accounting.fixed_assets.field.residual_value')"
                       :hint="__('accounting.fixed_assets.hint.residual_value')"
                       :value="old('residual_value', $fixedAsset?->residual_value?->getAmount() ?? '0.00')"
                       :readonly="$frozen" />
        <x-input-field name="useful_life_months" type="number" min="1" max="1200" :required="! $classDefaults"
                       :label="__('accounting.fixed_assets.column.useful_life')"
                       :hint="$classDefaults ? __('accounting.fixed_assets.hint.useful_life_class') : __('accounting.fixed_assets.hint.useful_life')"
                       :value="old('useful_life_months', (string) ($fixedAsset?->useful_life_months ?? ($classDefaults ? '' : 36)))"
                       :readonly="$frozen" />

        <x-select-field name="depreciation_method" :label="__('accounting.fixed_assets.field.method')" span="2">
            @if ($classDefaults)
                <option value="">{{ __('accounting.fixed_assets.method_from_class') }}</option>
            @endif
            @foreach ($methods as $method)
                <option value="{{ $method->value }}" @selected(old('depreciation_method', $fixedAsset?->depreciation_method?->value ?? ($classDefaults ? '' : 'linear')) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
        {{-- Degressive AfA (MVP-980): Satz in Prozent, nur mit Methode „degressiv“. --}}
        <x-input-field name="declining_rate" type="number" min="0.01" max="30" step="0.01" inputmode="decimal" span="2"
                       :label="__('accounting.fixed_assets.field.declining_rate')"
                       :hint="__('accounting.fixed_assets.hint.declining_rate')"
                       :value="old('declining_rate', $fixedAsset?->declining_rate?->getNumericValue())"
                       :readonly="$frozen" />
    </x-form-group>

    <x-form-group :legend="__('accounting.fixed_assets.section.accounts')" :description="__('accounting.fixed_assets.hint.accounts')" icon="account_tree" tone="ghost" cols="2">
        <x-select-field name="asset_account" :label="__('accounting.fixed_assets.field.asset_account')">
            <option value="">{{ __('accounting.fixed_assets.account_from_rule') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('asset_account', $fixedAsset?->assetAccount?->sqid ?? '') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="depreciation_account" :label="__('accounting.fixed_assets.field.depreciation_account')">
            <option value="">{{ __('accounting.fixed_assets.account_from_rule') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected(old('depreciation_account', $fixedAsset?->depreciationAccount?->sqid ?? '') === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-textarea-field name="note" :label="__('accounting.ledger.field.note')" rows="2" maxlength="2000"
                      :value="old('note', $fixedAsset?->note)" />
</x-modal>

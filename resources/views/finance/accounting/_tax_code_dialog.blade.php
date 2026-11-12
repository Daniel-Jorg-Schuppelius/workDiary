{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tax_code_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kennziffern eines Steuerkennzeichens (Feature 125, MVP-688). Sie ordnen die
  Zeile den Feldern der Voranmeldung zu — eine Abgleichhilfe, kein Vordruck.

  Seit MVP-1558 auch Anlegen und Ändern: Satz, Richtung und Steuerkonto eines
  bebuchten Kennzeichens wechseln nur zum Stichtag (neue Fassung).
--}}
<x-modal
    :title="$taxCode ? $taxCode->code . ' — ' . $taxCode->name : __('accounting.tax_codes.action.add')"
    icon="tag"
    :action="$taxCode ? route('finance.accounting.tax-codes.update', $taxCode) : route('finance.accounting.tax-codes.store')"
    :method="$taxCode ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    @if ($inUse)
        <div class="alert bg-info/10 border-info/30 text-sm" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.tax_codes.in_use_hint', ['date' => $taxCode->valid_from->fdate()]) }}</span>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        @if ($taxCode)
            <x-input-field name="code_display" type="text" disabled
                           :label="__('accounting.tax_codes.field.code')"
                           :value="$taxCode->code" />
        @else
            <x-input-field name="code" type="text" required maxlength="16"
                           :label="__('accounting.tax_codes.field.code')"
                           :hint="__('accounting.tax_codes.hint.code')"
                           :value="old('code', '')" />
        @endif
        <x-input-field name="name" type="text" required maxlength="191"
                       :label="__('accounting.ledger.column.name')"
                       :value="old('name', $taxCode->name ?? '')" />

        <x-select-field name="direction" :label="__('accounting.reports.column.direction')">
            @foreach ($directions as $direction)
                <option value="{{ $direction->value }}" @selected(old('direction', $taxCode?->direction->value ?? 'output') === $direction->value)>{{ $direction->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="rate" type="number" step="0.01" min="0" max="100" required
                       :label="__('accounting.tax_codes.field.rate')"
                       :value="old('rate', $taxCode->rate ?? '19.00')" />

        @php($selectedAccount = old('tax_account', $accounts->firstWhere('id', $taxCode?->tax_account_id)?->sqid ?? ''))
        <x-select-field name="tax_account" :label="__('accounting.tax_codes.field.tax_account')" :hint="__('accounting.tax_codes.hint.tax_account')">
            <option value="">—</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected($selectedAccount === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="valid_from" type="date" required
                       :label="__('accounting.tax_codes.field.valid_from')"
                       :hint="$inUse ? __('accounting.tax_codes.hint.valid_from') : null"
                       :value="old('valid_from', $taxCode?->valid_from?->toDateString() ?? now()->startOfYear()->toDateString())" />

        <x-select-field name="tax_category" :label="__('accounting.tax_codes.field.tax_category')" :hint="__('accounting.tax_codes.hint.tax_category')" span="2">
            <option value="">—</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(old('tax_category', $taxCode->tax_category ?? '') === $category)>{{ __('accounting.tax_codes.category.' . $category) }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="ustva_base_field" type="text" maxlength="8"
                       :label="__('accounting.filing.fields.column.base')"
                       :hint="__('accounting.filing.fields.hint.base')"
                       :value="old('ustva_base_field', $taxCode->ustva_base_field ?? '')" />
        <x-input-field name="ustva_tax_field" type="text" maxlength="8"
                       :label="__('accounting.filing.fields.column.tax')"
                       :hint="__('accounting.filing.fields.hint.tax')"
                       :value="old('ustva_tax_field', $taxCode->ustva_tax_field ?? '')" />

        {{-- BU-Schlüssel des DATEV-Buchungsstapels (MVP-1622). --}}
        <x-input-field name="datev_tax_key" type="text" maxlength="4" inputmode="numeric"
                       :label="__('accounting.tax_codes.field.datev_tax_key')"
                       :hint="__('accounting.tax_codes.hint.datev_tax_key')"
                       :value="old('datev_tax_key', $taxCode->datev_tax_key ?? '')" />
    </div>

    {{-- Steuerschuld des Empfängers (MVP-1621): nur für die Richtung „Umsatz- und Vorsteuer“. --}}
    <x-form-group-collapsible :legend="__('accounting.tax_codes.self_assessed.legend')" icon="sync_alt" tone="ghost" cols="2"
                              :description="__('accounting.tax_codes.self_assessed.hint')"
                              :fields="['input_tax_account', 'tax_case', 'ustva_input_tax_field']"
                              :values="['input_tax_account' => $taxCode?->input_tax_account_id, 'tax_case' => $taxCode?->tax_case?->value, 'ustva_input_tax_field' => $taxCode?->ustva_input_tax_field]">
        @php($selectedInputAccount = old('input_tax_account', $accounts->firstWhere('id', $taxCode?->input_tax_account_id)?->sqid ?? ''))
        <x-select-field name="input_tax_account" :label="__('accounting.tax_codes.field.input_tax_account')">
            <option value="">—</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}" @selected($selectedInputAccount === $account->sqid)>{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="tax_case" :label="__('accounting.tax_codes.field.tax_case')" :hint="__('accounting.tax_codes.hint.tax_case')">
            <option value="">—</option>
            @foreach ($taxCases as $case)
                <option value="{{ $case->value }}" @selected(old('tax_case', $taxCode?->tax_case?->value ?? '') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="ustva_input_tax_field" type="text" maxlength="8"
                       :label="__('accounting.tax_codes.field.ustva_input_tax_field')"
                       :value="old('ustva_input_tax_field', $taxCode->ustva_input_tax_field ?? '')" />
    </x-form-group-collapsible>
</x-modal>

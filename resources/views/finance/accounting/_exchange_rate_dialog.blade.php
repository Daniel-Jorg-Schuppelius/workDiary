{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _exchange_rate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Monatskurs anlegen/ändern (MVP-1012); Währung und Monat bestimmen den Datensatz. Variablen: $rate (AccountingExchangeRate|null)
--}}
<x-modal
    :title="$rate !== null ? __('accounting.exchange_rates.action.edit') : __('accounting.exchange_rates.action.add')"
    :eyebrow="__('accounting.exchange_rates.title')"
    icon="currency_exchange" tone="primary"
    :action="route('finance.accounting.exchange-rates.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-form-group :legend="__('accounting.exchange_rates.title')" icon="currency_exchange" tone="primary" cols="2" :description="__('accounting.exchange_rates.hint.rate')">
        <x-input-field name="currency" :label="__('accounting.exchange_rates.field.currency')" required maxlength="3" class="uppercase"
                       :readonly="$rate !== null" :value="old('currency', $rate?->currency->value)" placeholder="USD" />
        <x-input-field name="period" type="month" :label="__('accounting.exchange_rates.field.period')" required
                       :readonly="$rate !== null" :value="old('period', $rate?->period->format('Y-m'))" />
        <x-input-field name="rate" type="number" step="0.000001" min="0.000001" required
                       :label="__('accounting.exchange_rates.field.rate')" :value="old('rate', $rate?->rate->getValue())" />
        <x-input-field name="source" maxlength="120" :label="__('accounting.exchange_rates.field.source')"
                       :value="old('source', $rate?->source ?? 'BMF')" />
    </x-form-group>
</x-modal>

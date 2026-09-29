{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _exchange_rate_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Monatskurse zeilenweise übernehmen (MVP-1012).
--}}
<x-modal
    :title="__('accounting.exchange_rates.action.import')"
    :eyebrow="__('accounting.exchange_rates.title')"
    icon="upload" tone="primary"
    :action="route('finance.accounting.exchange-rates.import')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('accounting.exchange_rates.action.import')">
    <x-form-group :legend="__('accounting.exchange_rates.action.import')" icon="upload" tone="primary" cols="1" :description="__('accounting.exchange_rates.hint.import')">
        <x-textarea-field name="import" rows="8" required maxlength="20000" :label="__('accounting.exchange_rates.field.import')"
                          :value="old('import')" placeholder="USD;2026-03;1,0823" />
        <x-input-field name="source" maxlength="120" :label="__('accounting.exchange_rates.field.source')" :value="old('source', 'BMF')" />
    </x-form-group>
</x-modal>

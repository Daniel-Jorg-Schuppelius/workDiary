{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fixed_asset_special_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sonder-AfA § 7g für ein Geschäftsjahr im Begünstigungszeitraum (MVP-981).
  Variablen: $fixedAsset, $years
--}}
<x-modal
    :title="__('accounting.fixed_assets.special.title')"
    :eyebrow="$fixedAsset->displayNo() . ' · ' . $fixedAsset->name"
    icon="bolt"
    :action="route('finance.accounting.fixed-assets.special.store', $fixedAsset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <p class="text-sm text-base-content/70">{{ __('accounting.fixed_assets.special.hint') }}</p>

    <x-select-field name="fiscal_year" :label="__('accounting.fixed_assets.schedule.year')" required>
        @foreach ($years as $year)
            <option value="{{ $year }}" @selected((int) old('fiscal_year', $years[0]) === $year)>{{ $year }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="depreciation_amount" type="number" min="0.01" step="0.01" inputmode="decimal" required
                   :label="__('accounting.fixed_assets.special.amount')"
                   :value="old('depreciation_amount')" />
    <x-input-field name="note" maxlength="255" :label="__('accounting.ledger.field.note')" :value="old('note')" />
</x-modal>

{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _line_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Position einer Gutschrift (MVP-1280) — nur am Entwurf. Variablen: $credit.
--}}
<x-modal
    :title="__('invoicing.self_billing.action.add_line')"
    :eyebrow="$credit->supplier->company ?: $credit->supplier->name"
    icon="playlist_add"
    tone="primary"
    :action="route('invoices.self-billing.lines.store', $credit)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('invoicing.self_billing.action.add_line')">

    <x-form-group :legend="__('invoicing.self_billing.field.line')" icon="playlist_add" tone="primary" cols="2">
        <x-input-field name="description" span="2" :label="__('invoicing.self_billing.field.description')" required maxlength="500" :value="old('description')" />
        <x-input-field name="quantity" type="number" step="0.0001" min="0.0001" :label="__('invoicing.self_billing.field.quantity')" required :value="old('quantity', '1')" />
        <x-input-field name="unit" :label="__('invoicing.self_billing.field.unit')" maxlength="20" :value="old('unit')" />
        <x-input-field name="unit_price" type="number" step="0.0001" :label="__('invoicing.self_billing.field.unit_price')" required :value="old('unit_price')" />
        <x-input-field name="tax_rate" type="number" step="0.01" min="0" max="99" :label="__('invoicing.self_billing.field.tax_rate')" :value="old('tax_rate', '19')" />
    </x-form-group>
</x-modal>

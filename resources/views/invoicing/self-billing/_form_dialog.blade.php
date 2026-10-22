{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Neue Gutschrift im Gutschriftsverfahren (MVP-1280). Wählbar sind nur
  Lieferanten mit hinterlegter Vereinbarung. Variablen: $suppliers, $selected.
--}}
<x-modal
    :title="__('invoicing.self_billing.action.create')"
    :eyebrow="__('invoicing.self_billing.title')"
    icon="receipt_long"
    tone="primary"
    :action="route('invoices.self-billing.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('invoicing.self_billing.action.create')">

    @if ($suppliers->isEmpty())
        <x-empty-state icon="handshake" :title="__('invoicing.self_billing.empty_suppliers')" :message="__('invoicing.self_billing.help.agreement')" />
    @else
        <x-form-group :legend="__('invoicing.self_billing.title')" icon="receipt_long" tone="primary" cols="1">
            <x-select-field name="supplier_id" :label="__('invoicing.self_billing.field.supplier')" required>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->sqid }}" @selected(old('supplier_id', $selected !== null && $selected === $supplier->id ? $supplier->sqid : null) === $supplier->sqid)>{{ $supplier->company ?: $supplier->name }}</option>
                @endforeach
            </x-select-field>
            <x-date-range layout="split" form-control from-name="service_from" to-name="service_until"
                          :from="old('service_from', now()->subMonthNoOverflow()->startOfMonth()->toDateString())"
                          :to="old('service_until', now()->subMonthNoOverflow()->endOfMonth()->toDateString())"
                          :from-label="__('invoicing.self_billing.field.service_from')" :to-label="__('invoicing.self_billing.field.service_until')" />
            <x-textarea-field name="note" :label="__('invoicing.self_billing.field.note')" rows="2" maxlength="2000" :value="old('note')" />
        </x-form-group>
    @endif
</x-modal>

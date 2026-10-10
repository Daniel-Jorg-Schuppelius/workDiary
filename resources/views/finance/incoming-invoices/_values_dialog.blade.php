{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _values_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Werte eines Klärfalls bzw. einer Erkennung erfassen (Feature 163, MVP-1110). --}}
@php($isOutgoing = $incoming->direction === \App\Enums\Billing\DocumentDirection::Outgoing)
<x-modal
    :title="__('Werte erfassen')"
    :eyebrow="$incoming->document?->title"
    icon="edit_note"
    :action="route('finance.incoming-invoices.values', $incoming)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('Rechnung')" icon="receipt_long" cols="2" :description="__('Werte am Original ablesen; das Original bleibt unverändert.')">
        <x-input-field name="invoice_number" :label="__('Rechnungsnummer')" :value="old('invoice_number', $incoming->invoice_number)" required />
        <x-input-field name="currency" :label="__('Währung')" :value="old('currency', $incoming->currency?->value ?? 'EUR')" required />
        <x-input-field name="issue_date" type="date" :label="__('Rechnungsdatum')" :value="old('issue_date', $incoming->issue_date?->toDateString())" required />
        <x-input-field name="due_date" type="date" :label="__('Fällig am')" :value="old('due_date', $incoming->due_date?->toDateString())" />
        <x-input-field name="party_name" :label="$isOutgoing ? __('Käufer') : __('Verkäufer')" :value="old('party_name', $isOutgoing ? $incoming->buyer_name : $incoming->seller_name)" />
        <x-input-field name="party_vat_id" :label="__('USt-IdNr.')" :value="old('party_vat_id', $isOutgoing ? $incoming->buyer_vat_id : $incoming->seller_vat_id)" />
    </x-form-group>

    <x-form-group :legend="__('Beträge')" icon="euro" cols="3">
        <x-input-field name="amount_net" :label="__('Netto')" inputmode="decimal" :value="old('amount_net', $incoming->amount_net?->getAmount())" />
        <x-input-field name="amount_tax" :label="__('Steuer')" inputmode="decimal" :value="old('amount_tax', $incoming->amount_tax?->getAmount())" />
        <x-input-field name="amount_gross" :label="__('Brutto')" inputmode="decimal" :value="old('amount_gross', $incoming->amount_gross?->getAmount())" required />
    </x-form-group>

    <x-validation-errors />
</x-modal>

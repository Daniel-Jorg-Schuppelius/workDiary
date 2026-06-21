{{-- Variablen: $invoice (Invoice), $item (InvoiceItem|null) --}}
@php
    $isEdit = $item->exists ?? false;
    $action = $isEdit
        ? route('invoices.items.update', [$invoice, $item])
        : route('invoices.items.store', $invoice);
    $method = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? __('Position bearbeiten') : __('Position hinzufügen');
@endphp
<x-modal
    :title="$title"
    :eyebrow="$invoice->number"
    icon="add"
    tone="primary"
    :action="$action"
    :method="$method"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Position')" icon="receipt_long" tone="primary" cols="2">
        <x-input-field name="description" :label="__('Beschreibung')" required maxlength="1000" span="2" :value="old('description', $item->description ?? '')" />
        <x-input-field name="service_date" type="date" :label="__('Leistungsdatum')" :value="old('service_date', optional($item->service_date ?? null)->format('Y-m-d'))" :hint="__('Bei mehreren Tagen Pflicht je Position.')" />
        <x-input-field name="quantity" type="number" :label="__('Menge')" required min="0" step="0.01" :value="old('quantity', (string) ($item->quantity ?? '1.00'))" />
        <x-input-field name="unit" :label="__('Einheit')" maxlength="32" :value="old('unit', $item->unit ?? __('invoicing.unit_hour'))" />
        <x-input-field name="unit_price" :label="__('Einzelpreis') . ' (' . $invoice->currency . ')'" type="number" required min="0" step="0.01" :value="old('unit_price', (string) ($item->unit_price ?? '0.00'))" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($item->position ?? ''))" />
    </x-form-group>
</x-modal>

{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        <x-article-picker :articles="$articles ?? collect()" :selected="$item->article_id ?? null" />
        <x-input-field name="description" :label="__('Beschreibung')" required maxlength="1000" span="2" :value="old('description', $item->description ?? '')" />
        <x-input-field name="service_date" type="date" :label="__('Leistungsdatum')" :value="old('service_date', optional($item->service_date ?? null)->format('Y-m-d'))" :hint="__('Bei mehreren Tagen Pflicht je Position.')" />
        <x-input-field name="quantity" type="number" :label="__('Menge')" required min="0" step="0.01" :value="old('quantity', (string) ($item->quantity ?? '1.00'))" />
        <x-input-field name="unit" :label="__('Einheit')" maxlength="32" :value="old('unit', $item->unit ?? __('invoicing.unit_hour'))" />
        <x-input-field name="unit_price" :label="__('Einzelpreis') . ' (' . $invoice->currency->value . ')'" type="number" required min="0" step="0.01" :value="old('unit_price', ($item->unit_price?->getAmount() ?? '0.00'))" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($item->position ?? ''))" />
        {{-- MVP-416: Positionsrabatt — Prozent ODER Betrag, nie beides. --}}
        <x-input-field name="discount_percent" type="number" :label="__('Rabatt %')" min="0" max="100" step="0.01" :value="old('discount_percent', $item->discount_percent?->getNumericValue() ?? '')" :hint="__('Prozent oder Betrag — nicht beides.')" />
        <x-input-field name="discount_amount" type="number" :label="__('Rabatt (Betrag)')" min="0" step="0.01" :value="old('discount_amount', $item->discount_amount?->getAmount() ?? '')" />
        <x-input-field name="tax_rate" type="number" :label="__('USt-Satz % (leer = Kopfsatz)')" min="0" max="99.99" step="0.01" :value="old('tax_rate', $item->tax_rate?->getNumericValue() ?? '')" />
        <x-select-field name="tax_category" :label="__('Steuerkategorie (EN 16931)')" :hint="__('Leer = aus Beleg abgeleitet (S/AE/Z/E/G).')">
            <option value="">{{ __('— automatisch —') }}</option>
            @foreach (['S' => 'S — Standard', 'AE' => 'AE — Reverse Charge', 'Z' => 'Z — Nullsatz', 'E' => 'E — befreit', 'G' => 'G — Export', 'K' => 'K — ig. Lieferung', 'O' => 'O — nicht steuerbar'] as $code => $label)
                <option value="{{ $code }}" @selected(old('tax_category', $item->tax_category ?? '') === $code)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>

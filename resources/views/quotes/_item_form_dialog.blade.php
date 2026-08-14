{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $quote (Quote), $item (QuoteItem|null) --}}
@php
    $isEdit = $item->exists ?? false;
    $action = $isEdit
        ? route('quotes.items.update', [$quote, $item])
        : route('quotes.items.store', $quote);
@endphp
<x-modal
    :title="$isEdit ? __('Position bearbeiten') : __('Position hinzufügen')"
    :eyebrow="$quote->number"
    icon="add"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Position')" icon="request_quote" tone="primary" cols="2">
        <x-input-field name="description" :label="__('Beschreibung')" required maxlength="1000" span="2" :value="old('description', $item->description ?? '')" />
        <x-input-field name="quantity" type="number" :label="__('Menge')" required min="0.001" step="0.001" :value="old('quantity', (string) ($item->quantity ?? '1.00'))" />
        <x-input-field name="unit" :label="__('Einheit')" maxlength="20" :value="old('unit', $item->unit ?? __('invoicing.unit_hour'))" />
        <x-input-field name="unit_price" type="number" :label="__('Einzelpreis (EUR)')" required step="0.01" :value="old('unit_price', ($item->unit_price?->getAmount() ?? '0.00'))" />
        {{-- MVP-416: Positionsrabatt — Prozent ODER Betrag, nie beides. --}}
        <x-input-field name="discount_percent" type="number" :label="__('Rabatt %')" min="0" max="100" step="0.01" :value="old('discount_percent', $item->discount_percent?->getNumericValue() ?? '')" :hint="__('Prozent oder Betrag — nicht beides.')" />
        <x-input-field name="discount_amount" type="number" :label="__('Rabatt (Betrag)')" min="0" step="0.01" :value="old('discount_amount', $item->discount_amount?->getAmount() ?? '')" />
        <x-input-field name="tax_rate" type="number" :label="__('USt-Satz % (leer = Standard)')" min="0" max="99" step="0.01" :value="old('tax_rate', $item->tax_rate?->getNumericValue() ?? '')" />
        <label class="label cursor-pointer justify-start gap-2" style="grid-column: span 2;">
            <input type="hidden" name="optional" value="0">
            <input type="checkbox" name="optional" value="1" class="checkbox checkbox-sm" @checked(old('optional', (bool) ($item->optional ?? false)))>
            <span class="label-text">{{ __('Eventualposition (Option) — zählt erst nach Annahme') }}</span>
        </label>
    </x-form-group>
</x-modal>

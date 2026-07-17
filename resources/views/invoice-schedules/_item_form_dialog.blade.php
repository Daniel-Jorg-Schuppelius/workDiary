{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-415: Positionsvorlage. Variablen: $schedule, $item --}}
@php
    $isEdit = $item->exists ?? false;
    $action = $isEdit
        ? route('invoice-schedules.items.update', [$schedule, $item])
        : route('invoice-schedules.items.store', $schedule);
@endphp
<x-modal
    :title="$isEdit ? __('Position bearbeiten') : __('Position hinzufügen')"
    :eyebrow="$schedule->title"
    icon="receipt_long"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Position')" icon="receipt_long" tone="primary" cols="2">
        <x-input-field name="description" :label="__('Beschreibung')" required maxlength="1000" span="2"
                       :value="old('description', $item->description ?? '')"
                       :hint="__('Platzhalter: {zeitraum_von} und {zeitraum_bis}')" />
        <x-input-field name="quantity" type="number" :label="__('Menge')" required min="0.001" step="0.001" :value="old('quantity', (string) ($item->quantity ?? '1.00'))" />
        <x-input-field name="unit" :label="__('Einheit')" maxlength="32" :value="old('unit', $item->unit ?? '')" />
        <x-input-field name="unit_price" type="number" :label="__('Einzelpreis')" required min="0" step="0.01" :value="old('unit_price', (string) ($item->unit_price ?? '0.00'))" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($item->position ?? ''))" />
        <x-input-field name="discount_percent" type="number" :label="__('Rabatt %')" min="0" max="100" step="0.01" :value="old('discount_percent', $item->discount_percent ?? '')" :hint="__('Prozent oder Betrag — nicht beides.')" />
        <x-input-field name="discount_amount" type="number" :label="__('Rabatt (Betrag)')" min="0" step="0.01" :value="old('discount_amount', $item->discount_amount ?? '')" />
        <x-input-field name="tax_rate" type="number" :label="__('USt-Satz % (leer = Standard)')" min="0" max="99.99" step="0.01" :value="old('tax_rate', $item->tax_rate ?? '')" />
        <x-select-field name="tax_category" :label="__('Steuerkategorie (EN 16931)')" :hint="__('Leer = aus Beleg abgeleitet (S/AE/Z/E/G).')">
            <option value="">{{ __('— automatisch —') }}</option>
            @foreach (['S' => 'S — Standard', 'AE' => 'AE — Reverse Charge', 'Z' => 'Z — Nullsatz', 'E' => 'E — befreit', 'G' => 'G — Export', 'K' => 'K — ig. Lieferung', 'O' => 'O — nicht steuerbar'] as $code => $label)
                <option value="{{ $code }}" @selected(old('tax_category', $item->tax_category ?? '') === $code)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>

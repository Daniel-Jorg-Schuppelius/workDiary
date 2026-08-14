{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, $suppliers, $warehouses --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('procurement.action.create')"
    :eyebrow="__('procurement.title')"
    icon="shopping_cart"
    tone="primary"
    :action="route('purchase-orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('purchase-orders.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="shopping_cart" tone="primary" cols="2">
        <x-select-field name="supplier" :label="__('procurement.field.supplier')" required>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}">{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="warehouse" :label="__('procurement.field.warehouse')" required>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->sqid }}">{{ $warehouse->name }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="expected_at" type="date" :label="__('procurement.field.expected_at')" :value="old('expected_at')" />
        <x-input-field name="note" :label="__('procurement.field.note')" maxlength="2000" :value="old('note')" />
    </x-form-group>
</x-modal>

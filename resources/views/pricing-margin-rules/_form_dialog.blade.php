{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, $suppliers, $roundings --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('procurement.margin.action.new_rule')"
    :eyebrow="__('procurement.margin.title')"
    icon="percent"
    tone="primary"
    :action="route('pricing-margin-rules.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('pricing-margin-rules.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('procurement.margin.legend_scope')" icon="filter_alt" tone="primary" cols="2">
        <x-input-field name="name" :label="__('procurement.margin.col.name')" required maxlength="191" :value="old('name')" />
        <x-input-field name="priority" type="number" min="0" :label="__('procurement.margin.col.priority')" :value="old('priority', 0)" />

        <x-select-field name="supplier" :label="__('procurement.margin.field.supplier_optional')">
            <option value="">{{ __('procurement.margin.scope_all_suppliers') }}</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}">{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="category" :label="__('procurement.margin.field.category_optional')" maxlength="191" :value="old('category')" />
    </x-form-group>

    <x-form-group :legend="__('procurement.margin.legend_calc')" icon="calculate" tone="primary" cols="2">
        <x-input-field name="target_margin" type="number" step="0.001" min="0" max="99.9"
                       :label="__('procurement.margin.field.target_margin')" :value="old('target_margin')" />
        <x-input-field name="markup_percent" type="number" step="0.001" min="0"
                       :label="__('procurement.margin.field.markup_percent')" :value="old('markup_percent')" />
        <x-input-field name="min_margin" type="number" step="0.001" min="0" max="100"
                       :label="__('procurement.margin.field.min_margin')" :value="old('min_margin')" />
        <x-input-field name="min_sale_price" type="number" step="0.01" min="0"
                       :label="__('procurement.margin.field.min_sale_price')" :value="old('min_sale_price')" />
        <x-select-field name="rounding" :label="__('procurement.margin.col.rounding')" required>
            @foreach ($roundings as $rounding)
                <option value="{{ $rounding->value }}">{{ $rounding->label() }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
    <p class="text-sm opacity-70">{{ __('procurement.margin.calc_hint') }}</p>
</x-modal>

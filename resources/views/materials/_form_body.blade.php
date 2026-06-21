@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared form fields for Material --}}

<x-form-group :legend="__('Stammdaten')" icon="category" tone="primary" cols="2">
    <x-input-field name="name" :label="__('Name')" required span="2" :value="old('name', $material->name)" />
    <x-input-field name="sku" label="SKU" :value="old('sku', $material->sku)" />
    <x-input-field name="unit" :label="__('Einheit')" required :value="old('unit', $material->unit)" />
</x-form-group>

<x-form-group :legend="__('Preis & Status')" icon="payments" tone="info" cols="2">
    <x-input-field name="default_unit_price" type="number" step="0.0001" min="0" :label="__('EP netto')" :value="old('default_unit_price', $material->default_unit_price)" />
    <x-input-field name="tax_rate" type="number" step="0.01" min="0" max="100" label="USt %" :value="old('tax_rate', $material->tax_rate)" />
    @unless ($skipStatusControls)
    <x-checkbox-field name="is_active" :label="__('Aktiv')" :checked="old('is_active', $material->is_active)" :toggle="false" span="2" />
    @endunless
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

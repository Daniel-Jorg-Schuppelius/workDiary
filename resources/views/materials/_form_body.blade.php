@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared form fields for Material --}}

<x-form-group :legend="__('Stammdaten')" icon="category" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required value="{{ old('name', $material->name) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $material->sku) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Einheit') }} *</label>
        <input type="text" name="unit" required value="{{ old('unit', $material->unit) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Preis & Status')" icon="payments" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('EP netto') }}</label>
        <input type="number" step="0.0001" min="0" name="default_unit_price"
               value="{{ old('default_unit_price', $material->default_unit_price) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">USt %</label>
        <input type="number" step="0.01" min="0" max="100" name="tax_rate"
               value="{{ old('tax_rate', $material->tax_rate) }}" class="input input-bordered w-full">
    </div>
    @unless ($skipStatusControls)
    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked(old('is_active', $material->is_active))>
            <span class="fieldset-label">{{ __('Aktiv') }}</span>
        </label>
    </div>
    @endunless
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

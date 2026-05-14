@extends('layouts.app')
@section('title', __('Material'))
@section('content')
<div class="mx-auto max-w-xl p-4">
    <h1 class="mb-4 font-['Space_Grotesk'] text-xl font-semibold">{{ $material->exists ? __('Material bearbeiten') : __('Material anlegen') }}</h1>
    <form method="POST" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}"
          class="flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @csrf
        @if($material->exists) @method('PUT') @endif

        <label class="form-control">
            <span class="label-text">{{ __('Name') }}</span>
            <input type="text" name="name" required value="{{ old('name', $material->name) }}" class="input input-bordered">
        </label>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label class="form-control">
                <span class="label-text">SKU</span>
                <input type="text" name="sku" value="{{ old('sku', $material->sku) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Einheit') }}</span>
                <input type="text" name="unit" required value="{{ old('unit', $material->unit) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">USt %</span>
                <input type="number" step="0.01" min="0" max="100" name="tax_rate"
                       value="{{ old('tax_rate', $material->tax_rate) }}" class="input input-bordered">
            </label>
        </div>
        <label class="form-control">
            <span class="label-text">{{ __('EP netto') }}</span>
            <input type="number" step="0.0001" min="0" name="default_unit_price"
                   value="{{ old('default_unit_price', $material->default_unit_price) }}" class="input input-bordered">
        </label>
        <label class="label cursor-pointer justify-start gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked(old('is_active', $material->is_active))>
            <span class="label-text">{{ __('Aktiv') }}</span>
        </label>

        @foreach($errors->all() as $err)
            <div class="text-sm text-error">{{ $err }}</div>
        @endforeach

        <div class="flex justify-end gap-2">
            <a href="{{ route('materials.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
            <button class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</div>
@endsection

@php($asset = $asset ?? new \App\Models\Asset())

<x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Typ') }} *</label>
        <select name="asset_class" class="select select-bordered w-full" required>
            @foreach ($classOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('asset_class', $asset->asset_class?->value ?? $asset->asset_class) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Status') }} *</label>
        <select name="status" class="select select-bordered w-full" required>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $asset->status?->value ?? $asset->status ?? \App\Enums\Asset\AssetStatus::Active->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required value="{{ old('name', $asset->name) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Seriennummer') }}</label>
        <input type="text" name="serial_no" value="{{ old('serial_no', $asset->serial_no) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde') }}</label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">{{ __('Kein Kunde') }}</option>
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}" @selected((string) old('customer_id', $asset->customer_id) === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Standort') }}</label>
        <input type="text" name="location_text" value="{{ old('location_text', $asset->location_text) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

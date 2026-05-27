@php
    /** @var \App\Models\Asset $asset */
    /** @var array<int|string, string> $customers */
    /** @var array<string, string> $classOptions */
    /** @var array<string, string> $statusOptions */
    /** @var array<string, string> $categoryOptions */
    /** @var array{customer_id:?int, site_id:?int, building_id:?int, floor_id:?int, room_id:?int} $prefill */
    $asset = $asset ?? new \App\Models\Asset();
    $prefill = $prefill ?? ['customer_id' => null, 'site_id' => null, 'building_id' => null, 'floor_id' => null, 'room_id' => null];
    $pickerCustomers = collect($customers)
        ->map(fn ($name, $id) => (object) ['id' => (int) $id, 'name' => (string) $name])
        ->values();
@endphp

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
        <label class="fieldset-label">{{ __('Kategorie') }}</label>
        <select name="category_code" class="select select-bordered w-full">
            <option value="">{{ __('— ohne Kategorie —') }}</option>
            @foreach ($categoryOptions as $code => $label)
                <option value="{{ $code }}" @selected(old('category_code', $asset->category_code) === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Inventarnummer') }}</label>
        <input type="text" name="inventory_no" value="{{ old('inventory_no', $asset->inventory_no) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Hersteller') }}</label>
        <input type="text" name="manufacturer" value="{{ old('manufacturer', $asset->manufacturer) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Modell') }}</label>
        <input type="text" name="model" value="{{ old('model', $asset->model) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Seriennummer') }}</label>
        <input type="text" name="serial_no" value="{{ old('serial_no', $asset->serial_no) }}" class="input input-bordered w-full">
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Standort (Freitext)') }}</label>
        <input type="text" name="location_text" value="{{ old('location_text', $asset->location_text) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Verortung')" icon="location_on" tone="info" cols="2">
    <x-facility-picker
        with-room
        :customers="$pickerCustomers"
        :sites="$sites ?? collect()"
        :buildings="$buildings ?? collect()"
        :floors="$floors ?? collect()"
        :rooms="$rooms ?? collect()"
        :selected="$prefill" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="sticky_note_2" cols="1">
    <div class="fieldset md:col-span-2">
        <textarea name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes', $asset->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

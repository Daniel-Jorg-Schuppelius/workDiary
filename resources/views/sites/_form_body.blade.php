{{-- Shared form fields for Site (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Site|null $site
     * @var \Illuminate\Support\Collection<int, \App\Models\Customer> $customers
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="location_on" tone="primary" cols="2">
    <x-input-field name="customer_id" :label="__('Kunde')" required>
        <select name="customer_id" required class="select select-bordered w-full @error('customer_id') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected((int) old('customer_id', $site?->customer_id ?? request('customer')) === (int) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </x-input-field>
    <x-input-field name="code" :label="__('Code')" :value="old('code', $site?->code)" maxlength="32" />
    <x-input-field name="name" :label="__('Name')" required :value="old('name', $site?->name)" maxlength="160" autofocus :span="2" />
    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer gap-3 justify-start">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary"
                   @checked(old('is_active', $site?->is_active ?? true))>
            <span class="fieldset-label">{{ __('Aktiv') }}</span>
        </label>
    </div>
</x-form-group>

<x-form-group :legend="__('Adresse')" icon="home" tone="info" cols="2">
    <x-input-field name="address_street" :label="__('Straße / Hausnr.')" :value="old('address_street', $site?->address_street)" maxlength="160" :span="2" />
    <x-input-field name="address_zip" :label="__('PLZ')" :value="old('address_zip', $site?->address_zip)" maxlength="16" />
    <x-input-field name="address_city" :label="__('Ort')" :value="old('address_city', $site?->address_city)" maxlength="120" />
    <x-input-field name="country" :label="__('Land (ISO-2)')" :value="old('country', $site?->country)" maxlength="2" class="uppercase" />
</x-form-group>

<x-form-group :legend="__('Geo-Koordinaten')" icon="my_location" tone="ghost" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Breitengrad') }}</label>
        <input type="number" step="0.0000001" min="-90" max="90" name="geo_lat"
               value="{{ old('geo_lat', $site?->geo_lat) }}"
               class="input input-bordered w-full @error('geo_lat') input-error @enderror">
        @error('geo_lat')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Längengrad') }}</label>
        <input type="number" step="0.0000001" min="-180" max="180" name="geo_lng"
               value="{{ old('geo_lng', $site?->geo_lng) }}"
               class="input input-bordered w-full @error('geo_lng') input-error @enderror">
        @error('geo_lng')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <div class="fieldset">
        <textarea name="notes" rows="3" maxlength="2000"
                  class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror">{{ old('notes', $site?->notes) }}</textarea>
        @error('notes')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

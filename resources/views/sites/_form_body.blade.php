{{-- Shared form fields for Site (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Site|null $site
     * @var \Illuminate\Support\Collection<int, \App\Models\Customer> $customers
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="location_on" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde') }} *</label>
        <select name="customer_id" required class="select select-bordered w-full @error('customer_id') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected((int) old('customer_id', $site?->customer_id ?? request('customer')) === (int) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Code') }}</label>
        <input type="text" name="code" maxlength="32"
               value="{{ old('code', $site?->code) }}"
               class="input input-bordered w-full @error('code') input-error @enderror">
        @error('code')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required maxlength="160" autofocus
               value="{{ old('name', $site?->name) }}"
               class="input input-bordered w-full @error('name') input-error @enderror">
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
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
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Straße / Hausnr.') }}</label>
        <input type="text" name="address_street" maxlength="160"
               value="{{ old('address_street', $site?->address_street) }}"
               class="input input-bordered w-full @error('address_street') input-error @enderror">
        @error('address_street')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('PLZ') }}</label>
        <input type="text" name="address_zip" maxlength="16"
               value="{{ old('address_zip', $site?->address_zip) }}"
               class="input input-bordered w-full @error('address_zip') input-error @enderror">
        @error('address_zip')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ort') }}</label>
        <input type="text" name="address_city" maxlength="120"
               value="{{ old('address_city', $site?->address_city) }}"
               class="input input-bordered w-full @error('address_city') input-error @enderror">
        @error('address_city')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Land (ISO-2)') }}</label>
        <input type="text" name="country" maxlength="2"
               value="{{ old('country', $site?->country) }}"
               class="input input-bordered w-full uppercase @error('country') input-error @enderror">
        @error('country')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
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

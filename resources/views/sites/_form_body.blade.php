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
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $site?->customer_id ?? \App\Support\Sqid::decode(\App\Models\Customer::class, request('customer'))) ) === $c->sqid)>{{ $c->name }}</option>
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
    {{-- MVP-513 P0 (Feature 103): Feiertagsregelung am Einsatzort für Zuschläge. --}}
    <div class="fieldset">
        <label class="fieldset-label" for="site-holiday-provider">{{ __('Feiertags-Region') }}</label>
        <select id="site-holiday-provider" name="holiday_provider" class="select select-bordered w-full">
            <option value="">{{ __('Feiertagsregelung der Organisation') }}</option>
            @foreach (\App\Support\HolidayRegions::grouped() as $group => $providers)
                <optgroup label="{{ $group }}">
                    @foreach ($providers as $provider => $label)
                        <option value="{{ $provider }}" @selected(old('holiday_provider', $site?->holiday_provider) === $provider)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('holiday_provider')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Geo-Koordinaten')" icon="my_location" tone="ghost" cols="2">
    <x-input-field name="geo_lat" type="number" :label="__('Breitengrad')" step="0.0000001" min="-90" max="90" :value="old('geo_lat', $site?->geo_lat)" />
    <x-input-field name="geo_lng" type="number" :label="__('Längengrad')" step="0.0000001" min="-180" max="180" :value="old('geo_lng', $site?->geo_lng)" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <x-textarea-field name="notes" rows="3" maxlength="2000" :value="old('notes', $site?->notes)" />
</x-form-group>

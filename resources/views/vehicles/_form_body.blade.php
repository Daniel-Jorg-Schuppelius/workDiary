{{-- Shared form fields for Vehicle (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Vehicle|null $vehicle
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="directions_car" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kennzeichen') }} *</label>
        <input type="text" name="license_plate" required maxlength="32"
               value="{{ old('license_plate', $vehicle?->license_plate) }}"
               class="input input-bordered w-full @error('license_plate') input-error @enderror">
        @error('license_plate')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Bezeichnung') }}</label>
        <input type="text" name="label" maxlength="120"
               value="{{ old('label', $vehicle?->label) }}"
               class="input input-bordered w-full @error('label') input-error @enderror">
        @error('label')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Typ') }} *</label>
        <select name="vehicle_type" required class="select select-bordered w-full">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('vehicle_type', $vehicle?->vehicle_type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Antrieb') }} *</label>
        <select name="propulsion" required class="select select-bordered w-full">
            @foreach ($propulsions as $p)
                <option value="{{ $p->value }}" @selected(old('propulsion', $vehicle?->propulsion?->value) === $p->value)>{{ $p->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Eigentum') }} *</label>
        <select name="ownership" required class="select select-bordered w-full" x-model="ownership">
            @foreach ($ownerships as $o)
                <option value="{{ $o->value }}" @selected(old('ownership', $vehicle?->ownership?->value ?? 'owned') === $o->value)>{{ $o->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Standardfahrer') }}</label>
        <select name="default_user_id" class="select select-bordered w-full">
            <option value="">{{ __('— frei verfügbar —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('default_user_id', sqid(\App\Models\User::class, $vehicle?->default_user_id)) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Technik & Verbrauch')" icon="bolt" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Standard-Satz €/km') }}</label>
        <input type="number" step="0.0001" min="0" name="default_rate_per_km"
               value="{{ old('default_rate_per_km', $vehicle?->default_rate_per_km) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Tachostand (km)') }}</label>
        <input type="number" min="0" name="odometer_km"
               value="{{ old('odometer_km', $vehicle?->odometer_km) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Tankvolumen (l)') }}</label>
        <input type="number" step="0.01" min="0" name="tank_capacity_liters"
               value="{{ old('tank_capacity_liters', $vehicle?->tank_capacity_liters) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Akku-Kapazität (kWh)') }}</label>
        <input type="number" step="0.01" min="0" name="battery_capacity_kwh"
               value="{{ old('battery_capacity_kwh', $vehicle?->battery_capacity_kwh) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('WLTP-Verbrauch') }}</label>
        <input type="number" step="0.001" min="0" name="wltp_consumption"
               value="{{ old('wltp_consumption', $vehicle?->wltp_consumption) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<div x-show="ownership === 'rental'" x-cloak>
    <x-form-group :legend="__('Mietvertrag')" icon="key" tone="warning" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Anbieter') }}</label>
            <input type="text" name="rental_provider" maxlength="120"
                   value="{{ old('rental_provider', $vehicle?->rental_provider) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Tagessatz (€)') }}</label>
            <input type="number" step="0.01" min="0" name="rental_cost_per_day"
                   value="{{ old('rental_cost_per_day', $vehicle?->rental_cost_per_day) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Mietbeginn') }}</label>
            <input type="date" name="rental_start"
                   value="{{ old('rental_start', $vehicle?->rental_start?->toDateString()) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Mietende') }}</label>
            <input type="date" name="rental_end"
                   value="{{ old('rental_end', $vehicle?->rental_end?->toDateString()) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Inklusiv-km (gesamt)') }}</label>
            <input type="number" min="0" name="rental_included_km"
                   value="{{ old('rental_included_km', $vehicle?->rental_included_km) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Extrakosten €/km') }}</label>
            <input type="number" step="0.0001" min="0" name="rental_extra_cost_per_km"
                   value="{{ old('rental_extra_cost_per_km', $vehicle?->rental_extra_cost_per_km) }}"
                   class="input input-bordered w-full">
        </div>
    </x-form-group>
</div>

<x-form-group :legend="__('Notizen')" icon="edit_note" tone="ghost">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Notizen') }}</label>
        <textarea name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes', $vehicle?->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

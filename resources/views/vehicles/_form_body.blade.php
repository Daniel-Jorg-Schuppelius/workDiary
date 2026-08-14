{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for Vehicle (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Vehicle|null $vehicle
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="directions_car" tone="primary" cols="2">
    <x-input-field name="license_plate" :label="__('Kennzeichen')" required maxlength="32" :value="old('license_plate', $vehicle?->license_plate)" />
    <x-input-field name="label" :label="__('Bezeichnung')" maxlength="120" :value="old('label', $vehicle?->label)" />
    <x-select-field name="vehicle_type" :label="__('Typ')" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('vehicle_type', $vehicle?->vehicle_type?->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="propulsion" :label="__('Antrieb')" required>
        @foreach ($propulsions as $p)
            <option value="{{ $p->value }}" @selected(old('propulsion', $vehicle?->propulsion?->value) === $p->value)>{{ $p->label() }}</option>
        @endforeach
    </x-select-field>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Eigentum') }} *</label>
        <select name="ownership" required class="select select-bordered w-full" x-model="value">
            @foreach ($ownerships as $o)
                <option value="{{ $o->value }}" @selected(old('ownership', $vehicle?->ownership?->value ?? 'owned') === $o->value)>{{ $o->label() }}</option>
            @endforeach
        </select>
    </div>
    <x-select-field name="default_user_id" :label="__('Standardfahrer')">
        <option value="">{{ __('— frei verfügbar —') }}</option>
        @foreach ($users as $u)
            <option value="{{ $u->sqid }}" @selected((string) old('default_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $vehicle?->default_user_id)) === $u->sqid)>{{ $u->name }}</option>
        @endforeach
    </x-select-field>
</x-form-group>

<x-form-group :legend="__('Technik & Verbrauch')" icon="bolt" tone="info" cols="2">
    <x-input-field name="default_rate_per_km" type="number" :label="__('Standard-Satz €/km')" step="0.0001" min="0" :value="old('default_rate_per_km', $vehicle?->default_rate_per_km)" />
    <x-input-field name="odometer_km" type="number" :label="__('Tachostand (km)')" min="0" :value="old('odometer_km', $vehicle?->odometer_km)" />
    <x-input-field name="tank_capacity_liters" type="number" :label="__('Tankvolumen (l)')" step="0.01" min="0" :value="old('tank_capacity_liters', $vehicle?->tank_capacity_liters)" />
    <x-input-field name="battery_capacity_kwh" type="number" :label="__('Akku-Kapazität (kWh)')" step="0.01" min="0" :value="old('battery_capacity_kwh', $vehicle?->battery_capacity_kwh)" />
    <x-input-field name="wltp_consumption" type="number" :label="__('WLTP-Verbrauch')" step="0.001" min="0" :value="old('wltp_consumption', $vehicle?->wltp_consumption)" :span="2" />
</x-form-group>

<div x-show="is('rental')" x-cloak>
    <x-form-group :legend="__('Mietvertrag')" icon="key" tone="warning" cols="2">
        <x-input-field name="rental_provider" :label="__('Anbieter')" maxlength="120" :value="old('rental_provider', $vehicle?->rental_provider)" />
        <x-input-field name="rental_cost_per_day" type="number" :label="__('Tagessatz (€)')" step="0.01" min="0" :value="old('rental_cost_per_day', $vehicle?->rental_cost_per_day)" />
        <x-input-field name="rental_start" type="date" :label="__('Mietbeginn')" :value="old('rental_start', $vehicle?->rental_start?->toDateString())" />
        <x-input-field name="rental_end" type="date" :label="__('Mietende')" :value="old('rental_end', $vehicle?->rental_end?->toDateString())" />
        <x-input-field name="rental_included_km" type="number" :label="__('Inklusiv-km (gesamt)')" min="0" :value="old('rental_included_km', $vehicle?->rental_included_km)" />
        <x-input-field name="rental_extra_cost_per_km" type="number" :label="__('Extrakosten €/km')" step="0.0001" min="0" :value="old('rental_extra_cost_per_km', $vehicle?->rental_extra_cost_per_km)" />
    </x-form-group>
</div>

<x-form-group :legend="__('Notizen')" icon="edit_note" tone="ghost">
    <x-textarea-field name="notes" :label="__('Notizen')" rows="3" :value="old('notes', $vehicle?->notes)" />
</x-form-group>

<x-validation-errors />

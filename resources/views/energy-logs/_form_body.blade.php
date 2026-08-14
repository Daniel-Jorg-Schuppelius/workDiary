{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for EnergyLog --}}

<x-form-group :legend="__('Tank-/Ladevorgang')" icon="local_gas_station" tone="primary" cols="2">
    <x-select-field name="vehicle_id" :label="__('Fahrzeug')" required>
        <option value="">—</option>
        @foreach ($vehicles as $v)
            <option value="{{ $v->sqid }}" @selected((string) old('vehicle_id', \App\Support\Sqid::encode(\App\Models\Vehicle::class, $log?->vehicle_id ?? $defaultVehicleId)) === $v->sqid)>{{ $v->displayName() }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="energy_type" :label="__('Typ')" required>
        @foreach ($types as $type)
            <option value="{{ $type }}" @selected(old('energy_type', $log?->energy_type ?? 'fuel') === $type)>{{ __("values.$type") }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="fuel_kind" :label="__('Kraftstoff')">
        <option value="">—</option>
        @foreach ($fuelKinds as $k)
            <option value="{{ $k }}" @selected(old('fuel_kind', $log?->fuel_kind) === $k)>{{ __("values.$k") }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="charger_type" :label="__('Ladetyp')">
        <option value="">—</option>
        @foreach ($chargerTypes as $c)
            <option value="{{ $c }}" @selected(old('charger_type', $log?->charger_type) === $c)>{{ __("values.$c") }}</option>
        @endforeach
    </x-select-field>
</x-form-group>

<x-form-group :legend="__('Menge & Kosten')" icon="payments" tone="info" cols="2">
    <x-input-field name="quantity" type="number" :label="__('Menge')" required step="0.001" min="0" :value="old('quantity', $log?->quantity)" />
    <x-input-field name="cost_total" type="number" :label="__('Kosten gesamt €')" step="0.01" min="0" :value="old('cost_total', $log?->cost_total)" />
    <x-input-field name="odometer_km" type="number" :label="__('Tachostand')" min="0" :value="old('odometer_km', $log?->odometer_km)" />
    <x-input-field name="location_address" :label="__('Ort')" maxlength="255" :value="old('location_address', $log?->location_address)" />
</x-form-group>

<x-form-group :legend="__('Zeitraum & Ladestand')" icon="schedule" tone="success" cols="2">
    <x-input-field name="started_at" type="datetime-local" :label="__('Beginn')" required :value="old('started_at', $log?->started_at?->orgTz()->format('Y-m-d\TH:i'))" />
    <x-input-field name="ended_at" type="datetime-local" :label="__('Ende')" :value="old('ended_at', $log?->ended_at?->orgTz()->format('Y-m-d\TH:i'))" />
    <x-input-field name="soc_before" type="number" :label="__('SoC vorher (%)')" min="0" max="100" :value="old('soc_before', $log?->soc_before)" />
    <x-input-field name="soc_after" type="number" :label="__('SoC nachher (%)')" min="0" max="100" :value="old('soc_after', $log?->soc_after)" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="edit_note" tone="ghost" cols="1">
    <x-textarea-field name="notes" :label="__('Notizen')" rows="3" :value="old('notes', $log?->notes)" />
</x-form-group>

<x-validation-errors />

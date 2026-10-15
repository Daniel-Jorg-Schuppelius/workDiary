{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _trip_confirm_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $trip, $vehicles (TravelLogVehicle), $fleetVehicles, $tripKinds --}}
<x-modal
    :title="__('Ins Fahrtenbuch übernehmen')"
    :eyebrow="$trip->started_at->orgTz()->translatedFormat('d.m.Y') . ' · ' . $trip->started_at->orgTz()->format('H:i') . '–' . $trip->ended_at->orgTz()->format('H:i')"
    icon="directions_car"
    tone="primary"
    :action="route('location.review.trips.confirm', $trip)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Übernehmen')">

    <p class="text-sm">
        {{ $trip->from_label ?? '—' }} → {{ $trip->to_label ?? '—' }}
        · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $trip->distance_km, 2, withThousandsSeparator: true) }} km
        @if ($trip->distance_source !== \App\Models\Location\LocationPendingTrip::SOURCE_MATCH)
            <span class="text-warning">({{ __('Strecke aus der GPS-Spur ohne Kartenabgleich.') }})</span>
        @endif
    </p>

    <x-form-group :legend="__('Fahrt')" icon="directions_car" cols="2">
        <x-select-field name="vehicle" :label="__('Fahrzeug')" required>
            @foreach ($vehicles as $v)
                <option value="{{ $v->value }}" @selected(old('vehicle', \App\Enums\Travel\TravelLogVehicle::Company->value) === $v->value)>{{ $v->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="trip_kind" :label="__('Fahrtart')" required>
            @foreach ($tripKinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('trip_kind', \App\Enums\Travel\TripKind::Business->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="vehicle_id" :label="__('Fuhrpark-Fahrzeug (optional)')" :span="2">
            <option value="">—</option>
            @foreach ($fleetVehicles as $fv)
                <option value="{{ $fv->sqid }}" @selected((string) old('vehicle_id') === $fv->sqid)>{{ $fv->license_plate }} {{ $fv->label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="purpose" :label="__('Zweck')" :value="old('purpose', trim(($trip->from_label ?? '') . ' → ' . ($trip->to_label ?? ''), ' →'))" :span="2" />
        <x-input-field name="odometer_start_km" type="number" min="0" step="1" :label="__('Tachostand Beginn (km)')" :value="old('odometer_start_km')" />
        <x-input-field name="odometer_end_km" type="number" min="0" step="1" :label="__('Tachostand Ende (km)')" :value="old('odometer_end_km')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>

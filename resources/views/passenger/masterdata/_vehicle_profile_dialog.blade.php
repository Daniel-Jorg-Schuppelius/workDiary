{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _vehicle_profile_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Fahrzeugprofil anlegen/bearbeiten (MVP-456) — Betriebsarten, Geräte, Nachweise. --}}
@php
    $editing = $profile !== null;
    $selectedModes = collect(old('operation_modes', $profile?->operation_modes ?? []))->all();
@endphp
<x-modal
    :title="$editing ? __('passenger.masterdata.action.edit_vehicle_profile') : __('passenger.masterdata.action.create_vehicle_profile')"
    :eyebrow="__('passenger.masterdata.vehicle_profiles')"
    icon="directions_car"
    tone="primary"
    :action="$editing ? route('passenger-masterdata.vehicle-profiles.update', $profile) : route('passenger-masterdata.vehicle-profiles.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('passenger.masterdata.action.save')"
>
    <x-form-group :legend="__('passenger.field.vehicle')" icon="directions_car" tone="primary" cols="2">
        @if ($editing)
            <input type="hidden" name="vehicle_id" value="{{ $profile->vehicle?->sqid }}">
            <x-input-field name="vehicle_display" :label="__('passenger.field.vehicle')" :value="$profile->vehicle->license_plate ?? '—'" disabled />
        @else
            <x-select-field name="vehicle_id" :label="__('passenger.field.vehicle')" required>
                <option value="">…</option>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->sqid }}" @selected((string) old('vehicle_id') === $vehicle->sqid)>{{ $vehicle->license_plate }}</option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="order_number" :label="__('passenger.field.order_number')" :value="old('order_number', $profile?->order_number)" />
        <fieldset class="col-span-2">
            <legend class="text-sm font-medium">{{ __('passenger.field.operation_modes') }} *</legend>
            <div class="mt-1 flex flex-wrap gap-4">
                @foreach (\App\Enums\Passenger\RideOperationMode::cases() as $mode)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="operation_modes[]" value="{{ $mode->value }}" class="checkbox checkbox-sm"
                               @checked(in_array($mode->value, $selectedModes, true))>
                        {{ $mode->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <x-input-field name="passenger_seats" type="number" min="1" max="60" :label="__('passenger.field.passenger_seats')" :value="old('passenger_seats', $profile?->passenger_seats)" />
        <x-input-field name="wheelchair_places" type="number" min="0" max="10" :label="__('passenger.field.wheelchair_places')" :value="old('wheelchair_places', $profile?->wheelchair_places ?? 0)" />
        <div class="flex items-center gap-2">
            <input type="hidden" name="barrier_free" value="0">
            <input type="checkbox" id="profile-barrier-free" name="barrier_free" value="1" class="checkbox checkbox-sm" @checked((bool) old('barrier_free', $profile?->barrier_free))>
            <label for="profile-barrier-free" class="text-sm">{{ __('passenger.field.barrier_free') }}</label>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="large_capacity" value="0">
            <input type="checkbox" id="profile-large-capacity" name="large_capacity" value="1" class="checkbox checkbox-sm" @checked((bool) old('large_capacity', $profile?->large_capacity))>
            <label for="profile-large-capacity" class="text-sm">{{ __('passenger.field.large_capacity') }}</label>
        </div>
    </x-form-group>

    <x-form-group :legend="__('passenger.section.devices')" icon="speed" tone="primary" cols="2">
        <x-select-field name="meter_kind" :label="__('passenger.field.meter_kind')">
            <option value="">—</option>
            <option value="taxameter" @selected(old('meter_kind', $profile?->meter_kind) === 'taxameter')>{{ __('passenger.meter.taxameter') }}</option>
            <option value="wegstreckenzaehler" @selected(old('meter_kind', $profile?->meter_kind) === 'wegstreckenzaehler')>{{ __('passenger.meter.wegstreckenzaehler') }}</option>
        </x-select-field>
        <x-input-field name="meter_serial" :label="__('passenger.field.meter_serial')" :value="old('meter_serial', $profile?->meter_serial)" />
        <x-input-field name="meter_calibrated_until" type="date" :label="__('passenger.proof.meter_calibration')" :value="old('meter_calibrated_until', $profile?->meter_calibrated_until?->toDateString())" />
        <x-input-field name="tse_reference" :label="__('passenger.field.tse_reference')" :value="old('tse_reference', $profile?->tse_reference)" />
        <x-input-field name="bokraft_checked_until" type="date" :label="__('passenger.proof.bokraft')" :value="old('bokraft_checked_until', $profile?->bokraft_checked_until?->toDateString())" />
        <x-input-field name="hu_valid_until" type="date" :label="__('passenger.proof.hu')" :value="old('hu_valid_until', $profile?->hu_valid_until?->toDateString())" />
    </x-form-group>

    <x-validation-errors />
</x-modal>

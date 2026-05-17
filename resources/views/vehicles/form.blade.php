@extends('layouts.app')

@section('title', $vehicle ? __('Fahrzeug bearbeiten') : __('Neues Fahrzeug'))

@section('content')
    <div class="max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">{{ $vehicle ? __('Fahrzeug bearbeiten') : __('Neues Fahrzeug') }}</h1>

        <form method="POST" action="{{ $vehicle ? route('vehicles.update', $vehicle) : route('vehicles.store') }}"
              class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            @if ($vehicle) @method('PUT') @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Kennzeichen') }} *</span>
                    <input type="text" name="license_plate" required maxlength="32"
                           value="{{ old('license_plate', $vehicle?->license_plate) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Bezeichnung') }}</span>
                    <input type="text" name="label" maxlength="120"
                           value="{{ old('label', $vehicle?->label) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Typ') }} *</span>
                    <select name="vehicle_type" required class="select select-bordered select-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('vehicle_type', $vehicle?->vehicle_type) === $type)>{{ __($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Antrieb') }} *</span>
                    <select name="propulsion" required class="select select-bordered select-sm">
                        @foreach ($propulsions as $p)
                            <option value="{{ $p }}" @selected(old('propulsion', $vehicle?->propulsion) === $p)>{{ __($p) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Eigentum') }} *</span>
                    <select name="ownership" required class="select select-bordered select-sm">
                        @foreach ($ownerships as $o)
                            <option value="{{ $o }}" @selected(old('ownership', $vehicle?->ownership ?? 'owned') === $o)>{{ __($o) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Standardfahrer') }}</span>
                    <select name="default_user_id" class="select select-bordered select-sm">
                        <option value="">{{ __('— frei verfügbar —') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((int) old('default_user_id', $vehicle?->default_user_id) === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Standard-Satz €/km') }}</span>
                    <input type="number" step="0.0001" min="0" name="default_rate_per_km"
                           value="{{ old('default_rate_per_km', $vehicle?->default_rate_per_km) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Tankvolumen (l)') }}</span>
                    <input type="number" step="0.01" min="0" name="tank_capacity_liters"
                           value="{{ old('tank_capacity_liters', $vehicle?->tank_capacity_liters) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Akku-Kapazität (kWh)') }}</span>
                    <input type="number" step="0.01" min="0" name="battery_capacity_kwh"
                           value="{{ old('battery_capacity_kwh', $vehicle?->battery_capacity_kwh) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('WLTP-Verbrauch') }}</span>
                    <input type="number" step="0.001" min="0" name="wltp_consumption"
                           value="{{ old('wltp_consumption', $vehicle?->wltp_consumption) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Tachostand (km)') }}</span>
                    <input type="number" min="0" name="odometer_km"
                           value="{{ old('odometer_km', $vehicle?->odometer_km) }}"
                           class="input input-bordered input-sm">
                </label>
            </div>

            <fieldset class="rounded-box border border-base-300 p-3 space-y-3"
                      x-data="{ ownership: '{{ old('ownership', $vehicle?->ownership ?? 'owned') }}' }"
                      x-show="ownership === 'rental'" x-cloak>
                <legend class="px-1 text-sm font-medium">{{ __('Mietvertrag') }}</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('Anbieter') }}</span>
                        <input type="text" name="rental_provider" maxlength="120"
                               value="{{ old('rental_provider', $vehicle?->rental_provider) }}"
                               class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Tagessatz (€)') }}</span>
                        <input type="number" step="0.01" min="0" name="rental_cost_per_day"
                               value="{{ old('rental_cost_per_day', $vehicle?->rental_cost_per_day) }}"
                               class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Mietbeginn') }}</span>
                        <input type="date" name="rental_start"
                               value="{{ old('rental_start', $vehicle?->rental_start?->toDateString()) }}"
                               class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Mietende') }}</span>
                        <input type="date" name="rental_end"
                               value="{{ old('rental_end', $vehicle?->rental_end?->toDateString()) }}"
                               class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Inklusiv-km (gesamt)') }}</span>
                        <input type="number" min="0" name="rental_included_km"
                               value="{{ old('rental_included_km', $vehicle?->rental_included_km) }}"
                               class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Extrakosten €/km') }}</span>
                        <input type="number" step="0.0001" min="0" name="rental_extra_cost_per_km"
                               value="{{ old('rental_extra_cost_per_km', $vehicle?->rental_extra_cost_per_km) }}"
                               class="input input-bordered input-sm">
                    </label>
                </div>
            </fieldset>

            <label class="form-control">
                <span class="label-text">{{ __('Notizen') }}</span>
                <textarea name="notes" rows="3" class="textarea textarea-bordered textarea-sm">{{ old('notes', $vehicle?->notes) }}</textarea>
            </label>

            @if ($errors->any())
                <ul class="text-error text-sm">
                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            @endif

            <div class="flex justify-end gap-2">
                <a href="{{ route('vehicles.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
            </div>
        </form>
    </div>
@endsection

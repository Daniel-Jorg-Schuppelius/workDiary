@extends('layouts.app')

@section('title', $log ? __('Eintrag bearbeiten') : __('Neuer Tank-/Ladeeintrag'))

@section('content')
    <div class="max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">{{ $log ? __('Eintrag bearbeiten') : __('Neuer Tank-/Ladeeintrag') }}</h1>

        <form method="POST" action="{{ $log ? route('energy-logs.update', $log) : route('energy-logs.store') }}"
              class="space-y-4 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            @if ($log) @method('PUT') @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Fahrzeug') }} *</span>
                    <select name="vehicle_id" required class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}" @selected((int) old('vehicle_id', $log?->vehicle_id ?? $defaultVehicleId) === (int) $v->id)>{{ $v->displayName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Typ') }} *</span>
                    <select name="energy_type" required class="select select-bordered select-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('energy_type', $log?->energy_type ?? 'fuel') === $type)>{{ __($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Kraftstoff') }}</span>
                    <select name="fuel_kind" class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($fuelKinds as $k)
                            <option value="{{ $k }}" @selected(old('fuel_kind', $log?->fuel_kind) === $k)>{{ __($k) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ladetyp') }}</span>
                    <select name="charger_type" class="select select-bordered select-sm">
                        <option value="">—</option>
                        @foreach ($chargerTypes as $c)
                            <option value="{{ $c }}" @selected(old('charger_type', $log?->charger_type) === $c)>{{ __($c) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Menge') }} *</span>
                    <input type="number" step="0.001" min="0" name="quantity" required
                           value="{{ old('quantity', $log?->quantity) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Kosten gesamt €') }}</span>
                    <input type="number" step="0.01" min="0" name="cost_total"
                           value="{{ old('cost_total', $log?->cost_total) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Tachostand') }}</span>
                    <input type="number" min="0" name="odometer_km"
                           value="{{ old('odometer_km', $log?->odometer_km) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ort') }}</span>
                    <input type="text" name="location_address" maxlength="255"
                           value="{{ old('location_address', $log?->location_address) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Beginn') }} *</span>
                    <input type="datetime-local" name="started_at" required
                           value="{{ old('started_at', $log?->started_at?->format('Y-m-d\TH:i')) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ende') }}</span>
                    <input type="datetime-local" name="ended_at"
                           value="{{ old('ended_at', $log?->ended_at?->format('Y-m-d\TH:i')) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('SoC vorher (%)') }}</span>
                    <input type="number" min="0" max="100" name="soc_before"
                           value="{{ old('soc_before', $log?->soc_before) }}"
                           class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('SoC nachher (%)') }}</span>
                    <input type="number" min="0" max="100" name="soc_after"
                           value="{{ old('soc_after', $log?->soc_after) }}"
                           class="input input-bordered input-sm">
                </label>
            </div>

            <label class="form-control">
                <span class="label-text">{{ __('Notizen') }}</span>
                <textarea name="notes" rows="3" class="textarea textarea-bordered textarea-sm">{{ old('notes', $log?->notes) }}</textarea>
            </label>

            @if ($errors->any())
                <ul class="text-error text-sm">
                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            @endif

            <div class="flex justify-end gap-2">
                <a href="{{ route('energy-logs.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
            </div>
        </form>
    </div>
@endsection

{{-- Shared form fields for EnergyLog --}}

<x-form-group :legend="__('Tank-/Ladevorgang')" icon="local_gas_station" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Fahrzeug') }} *</label>
        <select name="vehicle_id" required class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($vehicles as $v)
                <option value="{{ $v->sqid }}" @selected((string) old('vehicle_id', \App\Support\Sqid::encode(\App\Models\Vehicle::class, $log?->vehicle_id ?? $defaultVehicleId)) === $v->sqid)>{{ $v->displayName() }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Typ') }} *</label>
        <select name="energy_type" required class="select select-bordered w-full">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('energy_type', $log?->energy_type ?? 'fuel') === $type)>{{ __("values.$type") }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kraftstoff') }}</label>
        <select name="fuel_kind" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($fuelKinds as $k)
                <option value="{{ $k }}" @selected(old('fuel_kind', $log?->fuel_kind) === $k)>{{ __("values.$k") }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ladetyp') }}</label>
        <select name="charger_type" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($chargerTypes as $c)
                <option value="{{ $c }}" @selected(old('charger_type', $log?->charger_type) === $c)>{{ __("values.$c") }}</option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Menge & Kosten')" icon="payments" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Menge') }} *</label>
        <input type="number" step="0.001" min="0" name="quantity" required
               value="{{ old('quantity', $log?->quantity) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kosten gesamt €') }}</label>
        <input type="number" step="0.01" min="0" name="cost_total"
               value="{{ old('cost_total', $log?->cost_total) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Tachostand') }}</label>
        <input type="number" min="0" name="odometer_km"
               value="{{ old('odometer_km', $log?->odometer_km) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ort') }}</label>
        <input type="text" name="location_address" maxlength="255"
               value="{{ old('location_address', $log?->location_address) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Zeitraum & Ladestand')" icon="schedule" tone="success" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beginn') }} *</label>
        <input type="datetime-local" name="started_at" required
               value="{{ old('started_at', $log?->started_at?->format('Y-m-d\TH:i')) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ende') }}</label>
        <input type="datetime-local" name="ended_at"
               value="{{ old('ended_at', $log?->ended_at?->format('Y-m-d\TH:i')) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('SoC vorher (%)') }}</label>
        <input type="number" min="0" max="100" name="soc_before"
               value="{{ old('soc_before', $log?->soc_before) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('SoC nachher (%)') }}</label>
        <input type="number" min="0" max="100" name="soc_after"
               value="{{ old('soc_after', $log?->soc_after) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="edit_note" tone="ghost" cols="1">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Notizen') }}</label>
        <textarea name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes', $log?->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

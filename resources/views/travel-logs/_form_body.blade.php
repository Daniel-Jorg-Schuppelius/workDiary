{{-- Shared form fields for TravelLog --}}

<x-form-group :legend="__('Fahrt')" icon="directions_car" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="date" required value="{{ old('date', $date) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Fahrzeug') }} *</label>
        <select name="vehicle" required class="select select-bordered w-full">
            @foreach ($vehicles as $v)
                <option value="{{ $v }}" @selected(old('vehicle', $log?->vehicle ?? 'private') === $v)>
                    {{ __($v) }}
                    @isset ($rates[$v]) ({{ number_format((float) $rates[$v], 2, ',', '.') }} €/km) @endisset
                </option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Von (Adresse)') }}</label>
        <input type="text" name="from_address" data-travel-geocode value="{{ old('from_address', $log?->from_address) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Nach (Adresse)') }}</label>
        <input type="text" name="to_address" data-travel-geocode value="{{ old('to_address', $log?->to_address) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Distanz & Satz')" icon="bar_chart" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Distanz (km, einfach)') }} *</label>
        <input type="number" step="0.01" min="0" name="distance_km" required
               value="{{ old('distance_km', $log?->distance_km) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Satz €/km (optional)') }}</label>
        <input type="number" step="0.0001" min="0" name="rate_per_km"
               value="{{ old('rate_per_km', $log?->rate_per_km) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('Auto aus Fahrzeugtyp') }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Start') }}</label>
        <input type="datetime-local" name="started_at"
               value="{{ old('started_at', $log?->started_at?->format('Y-m-d\TH:i')) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ende') }}</label>
        <input type="datetime-local" name="ended_at"
               value="{{ old('ended_at', $log?->ended_at?->format('Y-m-d\TH:i')) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Projekt (optional)') }}</label>
        <select name="project_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected(old('project_id', $log?->project_id) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde (optional)') }}</label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected(old('customer_id', $log?->customer_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Zweck') }}</label>
        <input type="text" name="purpose" value="{{ old('purpose', $log?->purpose) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Optionen & Notizen')" icon="edit_note" tone="ghost" cols="2">
    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="round_trip" value="0">
            <input type="checkbox" name="round_trip" value="1"
                   @checked(old('round_trip', $log?->round_trip)) class="checkbox checkbox-sm">
            <span class="fieldset-label">{{ __('Hin- und Rückfahrt (verdoppelt km)') }}</span>
        </label>
    </div>
    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="reimbursable" value="0">
            <input type="checkbox" name="reimbursable" value="1"
                   @checked(old('reimbursable', $log?->reimbursable ?? true)) class="checkbox checkbox-sm">
            <span class="fieldset-label">{{ __('Erstattungsfähig') }}</span>
        </label>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Notizen') }}</label>
        <textarea name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes', $log?->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

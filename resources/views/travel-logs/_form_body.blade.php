{{-- Shared form fields for TravelLog --}}

<x-form-group :legend="__('Fahrt')" icon="directions_car" tone="primary" cols="2">
    <x-input-field name="date" type="date" :label="__('Datum')" required :value="old('date', $date)" />
    <x-select-field name="vehicle" :label="__('Fahrzeug')" required>
        @foreach ($vehicles as $v)
            @php($value = $v instanceof \App\Enums\Travel\TravelLogVehicle ? $v->value : (string) $v)
            @php($label = $v instanceof \App\Enums\Travel\TravelLogVehicle ? $v->label() : __($value))
            <option value="{{ $value }}" @selected(old('vehicle', $log?->vehicle?->value ?? 'private') === $value)>
                {{ $label }}
                @isset ($rates[$value]) ({{ number_format((float) $rates[$value], 2, ',', '.') }} €/km) @endisset
            </option>
        @endforeach
    </x-select-field>
    <x-input-field name="from_address" :label="__('Von (Adresse)')" data-travel-geocode :value="old('from_address', $log?->from_address)" :span="2" />
    <x-input-field name="to_address" :label="__('Nach (Adresse)')" data-travel-geocode :value="old('to_address', $log?->to_address)" :span="2" />
</x-form-group>

<x-form-group :legend="__('Distanz & Satz')" icon="bar_chart" tone="info" cols="2">
    <x-input-field name="distance_km" type="number" :label="__('Distanz (km, einfach)')" required step="0.01" min="0" :value="old('distance_km', $log?->distance_km)" />
    <x-input-field name="rate_per_km" type="number" :label="__('Satz €/km (optional)')" step="0.0001" min="0" :value="old('rate_per_km', $log?->rate_per_km)" :placeholder="__('Auto aus Fahrzeugtyp')" />
    <x-date-range
        class="md:col-span-2"
        layout="split"
        type="time"
        :form-control="true"
        :linked="true"
        from-name="start_time"
        to-name="end_time"
        :from="old('start_time', $log?->started_at?->format('H:i'))"
        :to="old('end_time', $log?->ended_at?->format('H:i'))"
        :from-label="__('Start (Uhrzeit)')"
        :to-label="__('Ende (Uhrzeit)')"
        size=""
    />
    <p class="text-xs text-base-content/60 md:col-span-2">
        {{ __('Tipp: Endet die Fahrt nach Mitternacht? Einfach die kleinere Uhrzeit eintragen — der Folgetag wird automatisch ergänzt.') }}
    </p>
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <x-select-field name="project_id" :label="__('Projekt (optional)')" data-depends-on="customer_id">
        <option value="">—</option>
        @foreach ($projects as $p)
            <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $log?->project_id)) === $p->sqid)>{{ $p->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="customer_id" :label="__('Kunde (optional)')">
        <option value="">—</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $log?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="purpose" :label="__('Zweck')" :value="old('purpose', $log?->purpose)" :span="2" />
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
    <x-textarea-field name="notes" :label="__('Notizen')" rows="3" :value="old('notes', $log?->notes)" :span="2" />
</x-form-group>

<x-validation-errors />

{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for TravelLog --}}
{{-- $src: Vorbelegung — bearbeitete Fahrt oder (Stornofahrt) die stornierte Original-Fahrt --}}
@php($src = $log ?? ($correcting ?? null))

<x-form-group :legend="__('Fahrt')" icon="directions_car" tone="primary" cols="2">
    <x-input-field name="date" type="date" :label="__('Datum')" required :value="old('date', $date)" />
    <x-select-field name="vehicle" :label="__('Fahrzeug')" required>
        @foreach ($vehicles as $v)
            @php($value = $v instanceof \App\Enums\Travel\TravelLogVehicle ? $v->value : (string) $v)
            @php($label = $v instanceof \App\Enums\Travel\TravelLogVehicle ? $v->label() : __($value))
            <option value="{{ $value }}" @selected(old('vehicle', $src?->vehicle?->value ?? 'private') === $value)>
                {{ $label }}
                @isset ($rates[$value]) ({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $rates[$value], 2, withThousandsSeparator: true) }} €/km) @endisset
            </option>
        @endforeach
    </x-select-field>
    <x-select-field name="vehicle_id" :label="__('Fuhrpark-Fahrzeug (optional)')" :hint="__('Im Fahrtenbuch-Modus des Fahrzeugs sind km-Stände Pflicht; die Fahrt wird nach Tagesende festgeschrieben.')">
        <option value="">—</option>
        @foreach ($fleetVehicles as $fv)
            <option value="{{ $fv->sqid }}" @selected((string) old('vehicle_id', \App\Support\Sqid::encode(\App\Models\Vehicle::class, $src?->vehicle_id)) === $fv->sqid)>
                {{ $fv->displayName() }}{{ $fv->logbook_mode ? ' · ' . __('Fahrtenbuch-Modus') : '' }}
            </option>
        @endforeach
    </x-select-field>
    <x-select-field name="trip_kind" :label="__('Fahrtart')" required>
        @foreach ($tripKinds as $kind)
            <option value="{{ $kind->value }}" @selected(old('trip_kind', $src?->trip_kind?->value ?? 'business') === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="from_address" :label="__('Von (Adresse)')" data-travel-geocode :value="old('from_address', $src?->from_address)" :span="2" />
    <x-input-field name="to_address" :label="__('Nach (Adresse)')" data-travel-geocode :value="old('to_address', $src?->to_address)" :span="2" />
</x-form-group>

@if ($correcting ?? null)
    <x-form-group :legend="__('Stornofahrt')" icon="history" tone="warning" cols="1">
        <input type="hidden" name="corrects_travel_log_id" value="{{ $correcting->sqid }}">
        <p class="text-sm">{{ __('Die festgeschriebene Fahrt vom :date bleibt unverändert stehen; diese Stornofahrt ersetzt sie in Kette und Auswertung.', ['date' => $correcting->date?->fdate()]) }}</p>
        <x-textarea-field name="correction_reason" :label="__('Korrekturgrund')" rows="2" required :value="old('correction_reason')" />
    </x-form-group>
@endif

<x-form-group :legend="__('Distanz & Satz')" icon="bar_chart" tone="info" cols="2">
    <x-input-field name="distance_km" type="number" :label="__('Distanz (km, einfach)')" required step="0.01" min="0" :value="old('distance_km', $src?->distance_km)" />
    <x-input-field name="rate_per_km" type="number" :label="__('Satz €/km (optional)')" step="0.0001" min="0" :value="old('rate_per_km', $src?->rate_per_km)" :placeholder="__('Auto aus Fahrzeugtyp')" />
    <x-input-field name="odometer_start_km" type="number" :label="__('Tachostand Beginn (km)')" min="0" step="1" :value="old('odometer_start_km', $src?->odometer_start_km)" />
    <x-input-field name="odometer_end_km" type="number" :label="__('Tachostand Ende (km)')" min="0" step="1" :value="old('odometer_end_km', $src?->odometer_end_km)" />
    <x-date-range
        class="md:col-span-2"
        layout="split"
        type="time"
        :form-control="true"
        :linked="true"
        from-name="start_time"
        to-name="end_time"
        :from="old('start_time', $src?->started_at?->format('H:i'))"
        :to="old('end_time', $src?->ended_at?->format('H:i'))"
        :from-label="__('Start (Uhrzeit)')"
        :to-label="__('Ende (Uhrzeit)')"
        size=""
    />
    <p class="text-xs text-muted md:col-span-2">
        {{ __('Tipp: Endet die Fahrt nach Mitternacht? Einfach die kleinere Uhrzeit eintragen — der Folgetag wird automatisch ergänzt.') }}
    </p>
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <x-project-select :label="__('Projekt (optional)')" :projects="$projects"
        :selected="(string) old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $src?->project_id))"
        data-depends-on="customer_id" :data-parent="true" />
    <x-select-field name="customer_id" :label="__('Kunde (optional)')">
        <option value="">—</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $src?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="purpose" :label="__('Zweck')" :value="old('purpose', $src?->purpose)" :span="2" />
</x-form-group>

<x-form-group :legend="__('Optionen & Notizen')" icon="edit_note" tone="ghost" cols="2">
    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="round_trip" value="0">
            <input type="checkbox" name="round_trip" value="1"
                   @checked(old('round_trip', $src?->round_trip)) class="checkbox checkbox-sm">
            <span class="fieldset-label">{{ __('Hin- und Rückfahrt (verdoppelt km)') }}</span>
        </label>
    </div>
    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="reimbursable" value="0">
            <input type="checkbox" name="reimbursable" value="1"
                   @checked(old('reimbursable', $src?->reimbursable ?? true)) class="checkbox checkbox-sm">
            <span class="fieldset-label">{{ __('Erstattungsfähig') }}</span>
        </label>
    </div>
    <x-textarea-field name="notes" :label="__('Notizen')" rows="3" :value="old('notes', $src?->notes)" :span="2" />
</x-form-group>

<x-validation-errors />

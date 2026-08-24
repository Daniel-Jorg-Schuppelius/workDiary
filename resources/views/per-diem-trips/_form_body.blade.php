{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for PerDiemTrip --}}

@if (! empty($eligibility) && ! $eligibility['eligible'])
    <div role="alert" class="alert alert-warning mb-3">
        <x-icon name="warning" />
        <div class="flex-1 text-sm">
            {{ $eligibility['reason'] }}
            ({{ $eligibility['used_days'] }} / {{ $eligibility['limit_days'] }} {{ __('Tage') }})
        </div>
    </div>
@endif

<x-form-group :legend="__('Reise')" icon="restaurant_menu" tone="primary" cols="2">
    <x-select-field name="country" :label="__('Land')" required class="uppercase">
        @foreach ($countries as $iso)
            <option value="{{ $iso }}" @selected(old('country', $trip?->country ?? 'DE') === $iso)>{{ $iso }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="location" :label="__('Ort / Tätigkeitsstätte')" required maxlength="255" :value="old('location', $trip?->location)" :placeholder="__('z. B. Frankfurt am Main')" />
    <x-input-field name="purpose" :label="__('Zweck')" required span="2" maxlength="255" :value="old('purpose', $trip?->purpose)" :placeholder="__('z. B. Workshop, Onsite-Termin, Schulung …')" />
    <x-date-range layout="split" grid-class="contents" form-control size="" type="datetime-local"
                  from-name="started_at" to-name="ended_at"
                  from-id="started_at" to-id="ended_at" required
                  :from-label="__('Beginn')" :to-label="__('Ende')"
                  :from="old('started_at', $trip ? $trip->started_at->orgTz()->format('Y-m-d\\TH:i') : $date . 'T08:00')"
                  :to="old('ended_at', $trip ? $trip->ended_at->format('Y-m-d\\TH:i') : $date . 'T18:00')"
                  :from-error="$errors->first('started_at') ?: null"
                  :to-error="$errors->first('ended_at') ?: null" />
    <x-checkbox-field name="accommodation_provided" :label="__('Übernachtung wurde vom Arbeitgeber gestellt')" :checked="old('accommodation_provided', $trip?->accommodation_provided ?? false)" :toggle="false" span="2" />
    <x-textarea-field name="notes" :label="__('Notizen')" rows="2" maxlength="5000" span="2" :value="old('notes', $trip?->notes)" />
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <x-project-select :label="__('Projekt')" :projects="$projects"
        :selected="(string) old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $trip?->project_id))"
        data-depends-on="customer_id" :data-parent="true" />
    <x-select-field name="customer_id" :label="__('Kunde')">
        <option value="">—</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $trip?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="travel_log_id" :label="__('Bezug zum Fahrtenbuch')" span="2">
        <option value="">—</option>
        @foreach ($travelLogs as $tl)
            <option value="{{ $tl->sqid }}" @selected((string) old('travel_log_id', \App\Support\Sqid::encode(\App\Models\TravelLog::class, $trip?->travel_log_id)) === $tl->sqid)>
                {{ $tl->started_at?->fdate() }} · {{ $tl->from_address ?: '?' }} → {{ $tl->to_address ?: '?' }}
            </option>
        @endforeach
    </x-select-field>
</x-form-group>

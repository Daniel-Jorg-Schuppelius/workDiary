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
    <x-input-field name="started_at" type="datetime-local" :label="__('Beginn')" required :value="old('started_at', $trip ? $trip->started_at->orgTz()->format('Y-m-d\\TH:i') : $date . 'T08:00')" />
    <x-input-field name="ended_at" type="datetime-local" :label="__('Ende')" required :value="old('ended_at', $trip ? $trip->ended_at->format('Y-m-d\\TH:i') : $date . 'T18:00')" />
    <x-checkbox-field name="accommodation_provided" :label="__('Übernachtung wurde vom Arbeitgeber gestellt')" :checked="old('accommodation_provided', $trip?->accommodation_provided ?? false)" :toggle="false" span="2" />
    <x-textarea-field name="notes" :label="__('Notizen')" rows="2" maxlength="5000" span="2" :value="old('notes', $trip?->notes)" />
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <x-select-field name="project_id" :label="__('Projekt')" data-depends-on="customer_id">
        <option value="">—</option>
        @foreach ($projects as $p)
            <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $trip?->project_id)) === $p->sqid)>{{ $p->name }}</option>
        @endforeach
    </x-select-field>
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

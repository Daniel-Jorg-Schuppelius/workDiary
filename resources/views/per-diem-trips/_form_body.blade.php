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
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Land') }} *</label>
        <select name="country" required class="select select-bordered w-full uppercase">
            @foreach ($countries as $iso)
                <option value="{{ $iso }}" @selected(old('country', $trip?->country ?? 'DE') === $iso)>{{ $iso }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ort / Tätigkeitsstätte') }} *</label>
        <input type="text" name="location" required maxlength="255"
               value="{{ old('location', $trip?->location) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('z. B. Frankfurt am Main') }}">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Zweck') }} *</label>
        <input type="text" name="purpose" required maxlength="255"
               value="{{ old('purpose', $trip?->purpose) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('z. B. Workshop, Onsite-Termin, Schulung …') }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beginn') }} *</label>
        <input type="datetime-local" name="started_at" required
               value="{{ old('started_at', $trip ? $trip->started_at->format('Y-m-d\\TH:i') : $date . 'T08:00') }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ende') }} *</label>
        <input type="datetime-local" name="ended_at" required
               value="{{ old('ended_at', $trip ? $trip->ended_at->format('Y-m-d\\TH:i') : $date . 'T18:00') }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="accommodation_provided" value="0">
            <input type="checkbox" name="accommodation_provided" value="1"
                   @checked(old('accommodation_provided', $trip?->accommodation_provided ?? false))
                   class="checkbox checkbox-sm">
            <span class="fieldset-label">{{ __('Übernachtung wurde vom Arbeitgeber gestellt') }}</span>
        </label>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Notizen') }}</label>
        <textarea name="notes" rows="2" maxlength="5000"
                  class="textarea textarea-bordered w-full">{{ old('notes', $trip?->notes) }}</textarea>
    </div>
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Projekt') }}</label>
        <select name="project_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected(old('project_id', $trip?->project_id) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde') }}</label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected(old('customer_id', $trip?->customer_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Bezug zum Fahrtenbuch') }}</label>
        <select name="travel_log_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($travelLogs as $tl)
                <option value="{{ $tl->sqid }}" @selected((string) old('travel_log_id', sqid(\App\Models\TravelLog::class, $trip?->travel_log_id)) === $tl->sqid)>
                    {{ $tl->started_at?->format('d.m.Y') }} · {{ $tl->from_address ?: '?' }} → {{ $tl->to_address ?: '?' }}
                </option>
            @endforeach
        </select>
    </div>
</x-form-group>

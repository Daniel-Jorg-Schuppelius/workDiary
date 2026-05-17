{{-- Shared form fields for AdminTimeEntry create & edit --}}

@php
    /** @var \App\Models\TimeEntry|null $entry */
    $activityTypes = [
        \App\Models\TimeEntry::ACTIVITY_ADMIN     => __('Verwaltung'),
        \App\Models\TimeEntry::ACTIVITY_MEETING   => __('Besprechung'),
        \App\Models\TimeEntry::ACTIVITY_TRAINING  => __('Schulung'),
        \App\Models\TimeEntry::ACTIVITY_INTERNAL  => __('Intern'),
        \App\Models\TimeEntry::ACTIVITY_TRAVEL    => __('Anfahrt / Reise'),
        \App\Models\TimeEntry::ACTIVITY_BREAK     => __('Pause'),
        \App\Models\TimeEntry::ACTIVITY_OTHER     => __('Sonstiges'),
    ];
@endphp

<x-form-group :legend="__('Eckdaten')" icon="access_time" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="date" required value="{{ old('date', $entry?->date?->toDateString() ?? $date) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Dauer (Minuten)') }} *</label>
        <input type="number" name="minutes" min="1" max="1440" required value="{{ old('minutes', $entry?->minutes ?? 30) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Tätigkeitstyp') }} *</label>
        <select name="activity_type" required class="select select-bordered w-full">
            @foreach ($activityTypes as $key => $label)
                <option value="{{ $key }}" @selected(old('activity_type', $entry?->activity_type ?? 'admin') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Kategorie (optional)') }}</label>
        <select name="activity_category_id" class="select select-bordered w-full">
            <option value="">— {{ __('keine Kategorie') }} —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('activity_category_id', $entry?->activity_category_id) == $c->id)>
                    {{ $c->label }} ({{ $c->activity_type }})
                </option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Zeitraum (optional)')" icon="schedule" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beginn') }}</label>
        <input type="datetime-local" name="started_at" value="{{ old('started_at', $entry?->started_at?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ende') }}</label>
        <input type="datetime-local" name="ended_at" value="{{ old('ended_at', $entry?->ended_at?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full">
    </div>
    @if ($openAttendance)
        <input type="hidden" name="attendance_id" value="{{ $openAttendance->id }}">
        <p class="text-xs text-base-content/60 md:col-span-2">
            {{ __('Wird mit Stempelung verknüpft (seit :time).', ['time' => $openAttendance->started_at?->format('H:i')]) }}
        </p>
    @endif
</x-form-group>

<x-form-group :legend="__('Beschreibung')" icon="description" tone="ghost" cols="1">
    <div class="fieldset">
        <textarea name="description" rows="3" maxlength="500" class="textarea textarea-bordered w-full">{{ old('description', $entry?->description) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

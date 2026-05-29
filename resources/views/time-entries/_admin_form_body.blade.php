{{-- Shared form fields for AdminTimeEntry create & edit --}}

@php
    /** @var \App\Models\TimeEntry|null $entry */
    $activityTypes = [
        \App\Enums\TimeEntry\TimeEntryActivityType::Admin->value     => \App\Enums\TimeEntry\TimeEntryActivityType::Admin->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Meeting->value   => \App\Enums\TimeEntry\TimeEntryActivityType::Meeting->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Training->value  => \App\Enums\TimeEntry\TimeEntryActivityType::Training->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Internal->value  => \App\Enums\TimeEntry\TimeEntryActivityType::Internal->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Travel->value    => \App\Enums\TimeEntry\TimeEntryActivityType::Travel->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Break_->value    => \App\Enums\TimeEntry\TimeEntryActivityType::Break_->label(),
        \App\Enums\TimeEntry\TimeEntryActivityType::Other->value     => \App\Enums\TimeEntry\TimeEntryActivityType::Other->label(),
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
                <option value="{{ $key }}" @selected(old('activity_type', $entry?->activity_type?->value ?? 'admin') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Kategorie (optional)') }}</label>
        <select name="activity_category_id" class="select select-bordered w-full">
            <option value="">— {{ __('keine Kategorie') }} —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('activity_category_id', sqid(\App\Models\ActivityCategory::class, $entry?->activity_category_id)) === $c->sqid)>
                    {{ $c->label }} ({{ $c->activity_type->label() }})
                </option>
            @endforeach
        </select>
    </div>
</x-form-group>

<x-form-group :legend="__('Zeitraum (optional)')" icon="schedule" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beginn (Uhrzeit)') }}</label>
        <input type="time" name="start_time" value="{{ old('start_time', $entry?->started_at?->format('H:i')) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ende (Uhrzeit)') }}</label>
        <input type="time" name="end_time" value="{{ old('end_time', $entry?->ended_at?->format('H:i')) }}" class="input input-bordered w-full">
    </div>
    <p class="text-xs text-base-content/60 md:col-span-2">
        {{ __('Tipp: Endet die Zeit nach Mitternacht? Einfach die kleinere Uhrzeit eintragen — der Folgetag wird automatisch ergänzt.') }}
    </p>
    @if ($openAttendance)
        <input type="hidden" name="attendance_id" value="{{ $openAttendance->sqid }}">
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

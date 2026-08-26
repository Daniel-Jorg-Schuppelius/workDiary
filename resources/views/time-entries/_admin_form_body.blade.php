{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _admin_form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
    <x-input-field name="date"
                   :label="__('Datum')"
                   type="date"
                   value="{{ old('date', $entry?->date?->toDateString() ?? $date) }}"
                   required />
    <x-input-field name="minutes"
                   :label="__('Dauer (Minuten)')"
                   type="number"
                   value="{{ old('minutes', $entry?->minutes ?? 30) }}"
                   required
                   min="1"
                   max="1440" />
    <x-select-field span="2" name="activity_type" :label="__('Tätigkeitstyp')" required>
        @foreach ($activityTypes as $key => $label)
            <option value="{{ $key }}" @selected(old('activity_type', $entry?->activity_type?->value ?? 'admin') === $key)>{{ $label }}</option>
        @endforeach
    </x-select-field>
    <x-select-field span="2" name="activity_category_id" :label="__('Kategorie (optional)')">
        <option value="">— {{ __('keine Kategorie') }} —</option>
        @foreach ($categories as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('activity_category_id', \App\Support\Sqid::encode(\App\Models\ActivityCategory::class, $entry?->activity_category_id)) === $c->sqid)>
                {{ $c->label }} ({{ $c->activity_type->label() }})
            </option>
        @endforeach
    </x-select-field>
</x-form-group>

<x-form-group :legend="__('Zeitraum (optional)')" icon="schedule" tone="info" cols="2">
    <x-input-field name="start_time"
                   :label="__('Beginn (Uhrzeit)')"
                   type="time"
                   value="{{ old('start_time', $entry?->started_at?->orgTz()->format('H:i')) }}" />
    <x-input-field name="end_time"
                   :label="__('Ende (Uhrzeit)')"
                   type="time"
                   value="{{ old('end_time', $entry?->ended_at?->orgTz()->format('H:i')) }}" />
    <p class="text-xs text-muted md:col-span-2">
        {{ __('Tipp: Endet die Zeit nach Mitternacht? Einfach die kleinere Uhrzeit eintragen — der Folgetag wird automatisch ergänzt.') }}
    </p>
    @if ($openAttendance)
        <input type="hidden" name="attendance_id" value="{{ $openAttendance->sqid }}">
        <p class="text-xs text-muted md:col-span-2">
            {{ __('Wird mit Stempelung verknüpft (seit :time).', ['time' => $openAttendance->started_at?->ftime()]) }}
        </p>
    @endif
</x-form-group>

<x-form-group :legend="__('Beschreibung')" icon="description" tone="ghost" cols="1">
    <div class="fieldset">
        <textarea name="description" rows="3" maxlength="500" class="textarea textarea-bordered w-full">{{ old('description', $entry?->description) }}</textarea>
    </div>
    <div class="fieldset">
        <span class="fieldset-label">{{ __('Tags') }}</span>
        <x-tag-picker :tags="$allTags ?? []" :selected="$selectedTagIds ?? []" :recent="$recentTagIds ?? []" />
    </div>
</x-form-group>

<x-validation-errors />

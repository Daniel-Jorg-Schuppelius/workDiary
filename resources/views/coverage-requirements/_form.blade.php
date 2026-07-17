{{-- Variables: $dutyPlan, $requirement (CoverageRequirement|null), $isEdit --}}
@php
    $shiftTypes = \App\Models\ShiftType::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
    $qualifications = \App\Models\Qualification::query()
        ->orderBy('name')
        ->get();
    $weekdays = [
        ''  => __('— Wochentag —'),
        '0' => __('Sonntag'),
        '1' => __('Montag'),
        '2' => __('Dienstag'),
        '3' => __('Mittwoch'),
        '4' => __('Donnerstag'),
        '5' => __('Freitag'),
        '6' => __('Samstag'),
    ];
    $selectedQualIds = old('required_qualification_ids', $requirement?->required_qualification_ids ?? []);
@endphp

<x-validation-errors />

<x-form-group :legend="__('Schichttyp')" icon="bar_chart" tone="primary">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Schichttyp') }} *</div>
        <select name="shift_type_id" required class="select select-bordered w-full">
            <option value="">— {{ __('bitte wählen') }} —</option>
            @foreach ($shiftTypes as $st)
                <option value="{{ $st->sqid }}" @selected((string) old('shift_type_id', \App\Support\Sqid::encode(\App\Models\ShiftType::class, $requirement?->shift_type_id)) === $st->sqid)>
                    {{ $st->name }} ({{ $st->abbreviation }})
                </option>
            @endforeach
        </select>
    </label>
</x-form-group>

<x-form-group :legend="__('Wann')" icon="event" tone="info" cols="2">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Wochentag') }}</div>
        <select name="weekday" class="select select-bordered w-full">
            @foreach ($weekdays as $val => $label)
                <option value="{{ $val }}" @selected((string) old('weekday', $requirement?->weekday ?? '') === (string) $val)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <div class="fieldset-label text-xs text-base-content/60">{{ __('Leer = an allen Tagen') }}</div>
    </label>

    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Konkretes Datum') }}</div>
        <input type="date" name="specific_date"
               value="{{ old('specific_date', $requirement?->specific_date?->toDateString()) }}"
               class="input input-bordered w-full">
        <div class="fieldset-label text-xs text-base-content/60">{{ __('Überschreibt Wochentag-Regel') }}</div>
    </label>
</x-form-group>

<x-form-group :legend="__('Besetzung')" icon="group" tone="success" cols="2">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Min. Personen') }} *</div>
        <input type="number" name="min_staff" min="0" max="99" required
               value="{{ old('min_staff', $requirement?->min_staff ?? 1) }}"
               class="input input-bordered w-full">
    </label>

    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Max. Personen') }}</div>
        <input type="number" name="max_staff" min="0" max="99"
               value="{{ old('max_staff', $requirement?->max_staff) }}"
               class="input input-bordered w-full">
        <div class="fieldset-label text-xs text-base-content/60">{{ __('Leer = unbegrenzt') }}</div>
    </label>
</x-form-group>

@if ($qualifications->isNotEmpty())
    <x-form-group :legend="__('Erforderliche Qualifikationen')" icon="school" tone="warning">
        <div class="fieldset w-full">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($qualifications as $q)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="required_qualification_ids[]"
                               value="{{ $q->sqid }}"
                               class="checkbox checkbox-sm"
                               @checked(in_array($q->id, (array) $selectedQualIds, false))>
                        <span class="label-text">{{ $q->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </x-form-group>
@endif

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Notizen') }}</div>
        <textarea name="notes" maxlength="500" rows="2" class="textarea textarea-bordered w-full">{{ old('notes', $requirement?->notes) }}</textarea>
    </label>
</x-form-group>

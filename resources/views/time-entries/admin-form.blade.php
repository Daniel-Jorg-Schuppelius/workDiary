@extends('layouts.app')
@section('title', ($entry ? __('Verwaltungszeit bearbeiten') : __('Verwaltungszeit erfassen')) . ' — WorkDiary')
@section('nav-title', __('Verwaltungszeit'))

@php
    /** @var \App\Models\TimeEntry|null $entry */
    /** @var \Illuminate\Support\Collection $categories */
    /** @var string|null $date */
    /** @var \App\Models\Attendance|null $openAttendance */

    $activityTypes = [
        \App\Models\TimeEntry::ACTIVITY_ADMIN     => __('Verwaltung'),
        \App\Models\TimeEntry::ACTIVITY_MEETING   => __('Besprechung'),
        \App\Models\TimeEntry::ACTIVITY_TRAINING  => __('Schulung'),
        \App\Models\TimeEntry::ACTIVITY_INTERNAL  => __('Intern'),
        \App\Models\TimeEntry::ACTIVITY_TRAVEL    => __('Anfahrt / Reise'),
        \App\Models\TimeEntry::ACTIVITY_BREAK     => __('Pause'),
        \App\Models\TimeEntry::ACTIVITY_OTHER     => __('Sonstiges'),
    ];

    $action = $entry
        ? route('admin-time-entries.update', $entry)
        : route('admin-time-entries.store');
@endphp

@section('content')
    <div class="mx-auto w-full max-w-2xl space-y-4 px-4 py-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="font-['Space_Grotesk'] text-xl font-bold">
                {{ $entry ? __('Verwaltungszeit bearbeiten') : __('Verwaltungszeit erfassen') }}
            </h1>
            <a href="{{ route('today.show', ['date' => $date]) }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-error">
                <ul class="list-disc pl-4 text-sm">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="grid gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @csrf
            @if ($entry) @method('PUT') @endif

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Datum') }}</span>
                    <input type="date" name="date" required value="{{ old('date', $entry?->date?->toDateString() ?? $date) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Dauer (Minuten)') }}</span>
                    <input type="number" name="minutes" min="1" max="1440" required value="{{ old('minutes', $entry?->minutes ?? 30) }}" class="input input-bordered input-sm">
                </label>
            </div>

            <label class="form-control">
                <span class="label-text text-xs">{{ __('Tätigkeitstyp') }}</span>
                <select name="activity_type" required class="select select-bordered select-sm">
                    @foreach ($activityTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('activity_type', $entry?->activity_type ?? 'admin') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text text-xs">{{ __('Kategorie (optional)') }}</span>
                <select name="activity_category_id" class="select select-bordered select-sm">
                    <option value="">— {{ __('keine Kategorie') }} —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('activity_category_id', $entry?->activity_category_id) == $c->id)>
                            {{ $c->label }} ({{ $c->activity_type }})
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Beginn (optional)') }}</span>
                    <input type="datetime-local" name="started_at" value="{{ old('started_at', $entry?->started_at?->format('Y-m-d\TH:i')) }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Ende (optional)') }}</span>
                    <input type="datetime-local" name="ended_at" value="{{ old('ended_at', $entry?->ended_at?->format('Y-m-d\TH:i')) }}" class="input input-bordered input-sm">
                </label>
            </div>

            @if ($openAttendance)
                <input type="hidden" name="attendance_id" value="{{ $openAttendance->id }}">
                <p class="text-xs text-base-content/60">
                    {{ __('Wird mit Stempelung verknüpft (seit :time).', ['time' => $openAttendance->started_at?->format('H:i')]) }}
                </p>
            @endif

            <label class="form-control">
                <span class="label-text text-xs">{{ __('Beschreibung') }}</span>
                <textarea name="description" rows="3" maxlength="500" class="textarea textarea-bordered">{{ old('description', $entry?->description) }}</textarea>
            </label>

            <div class="flex justify-end gap-2">
                @if ($entry)
                    <form method="POST" action="{{ route('admin-time-entries.destroy', $entry) }}" class="inline">
                        @csrf @method('DELETE')
                        <button class="btn btn-ghost btn-sm text-error" onclick="return confirm('{{ __('Wirklich löschen?') }}')">{{ __('Löschen') }}</button>
                    </form>
                @endif
                <button type="submit" class="btn btn-primary btn-sm">{{ $entry ? __('Speichern') : __('Erfassen') }}</button>
            </div>
        </form>
    </div>
@endsection

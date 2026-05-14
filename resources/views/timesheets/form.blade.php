@extends('layouts.app')
@section('title', __('Stundenzettel') . ' – ' . __('Kopfdaten'))

@section('content')
<div class="mx-auto w-full max-w-xl p-4">
    <h1 class="mb-4 font-['Space_Grotesk'] text-xl font-semibold">{{ __('Stundenzettel') }}</h1>

    <form method="POST"
          action="{{ $timesheet->exists ? route('projects.timesheets.update', [$project, $timesheet]) : route('projects.timesheets.store', $project) }}"
          class="flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @csrf
        @if($timesheet->exists) @method('PUT') @endif

        <label class="form-control">
            <span class="label-text">{{ __('Datum') }}</span>
            <input type="date" name="work_date" required
                   value="{{ old('work_date', optional($timesheet->work_date)->format('Y-m-d')) }}"
                   class="input input-bordered">
        </label>

        <label class="form-control">
            <span class="label-text">{{ __('Kunde – Name') }}</span>
            <input type="text" name="customer_name" maxlength="255"
                   value="{{ old('customer_name', $timesheet->customer_name) }}"
                   class="input input-bordered">
        </label>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('Rolle / Funktion') }}</span>
                <input type="text" name="customer_role" maxlength="255"
                       value="{{ old('customer_role', $timesheet->customer_role) }}"
                       class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('E-Mail') }}</span>
                <input type="email" name="customer_email" maxlength="255"
                       value="{{ old('customer_email', $timesheet->customer_email) }}"
                       class="input input-bordered">
            </label>
        </div>

        <label class="form-control">
            <span class="label-text">{{ __('Notizen') }}</span>
            <textarea name="notes" rows="4" class="textarea textarea-bordered">{{ old('notes', $timesheet->notes) }}</textarea>
        </label>

        @error('work_date')<div class="text-sm text-error">{{ $message }}</div>@enderror

        <div class="flex justify-end gap-2">
            <a href="{{ $timesheet->exists ? route('projects.timesheets.show', [$project, $timesheet]) : route('projects.show', $project) }}"
               class="btn btn-ghost">{{ __('Abbrechen') }}</a>
            <button class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', __('Schicht bearbeiten'))
@section('nav-title', __('Schicht bearbeiten'))

@section('content')
    <div class="space-y-6">
        @if ($errors->any())
            <div class="alert alert-error"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('scheduled-shifts.update', $shift) }}" class="space-y-4 max-w-2xl">
            @csrf @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('Mitarbeiter') }} *</span>
                    <select name="user_id" required class="select select-bordered">
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id', $shift->user_id) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Schichttyp') }}</span>
                    <select name="shift_type_id" class="select select-bordered">
                        <option value="">—</option>
                        @foreach ($types as $t)
                            <option value="{{ $t->id }}" @selected(old('shift_type_id', $shift->shift_type_id) == $t->id)>{{ $t->name }} ({{ $t->abbreviation }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Datum') }} *</span>
                    <input type="date" name="date" required value="{{ old('date', $shift->date->format('Y-m-d')) }}" class="input input-bordered" />
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Status') }}</span>
                    <select name="status" class="select select-bordered">
                        @foreach (\App\Models\ScheduledShift::$statuses as $s)
                            <option value="{{ $s }}" @selected(old('status', $shift->status) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Beginn') }}</span>
                    <input type="time" name="start_time" value="{{ old('start_time', $shift->start_time) }}" class="input input-bordered" />
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('Ende') }}</span>
                    <input type="time" name="end_time" value="{{ old('end_time', $shift->end_time) }}" class="input input-bordered" />
                </label>
                <label class="form-control sm:col-span-2">
                    <span class="label-text">{{ __('Notiz') }}</span>
                    <textarea name="note" rows="3" class="textarea textarea-bordered">{{ old('note', $shift->note) }}</textarea>
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
                <a href="{{ route('scheduled-shifts.show', $shift) }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
            </div>
        </form>
    </div>
@endsection

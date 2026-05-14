@extends('layouts.app')
@section('title', __('Arbeitszeit-Modell'))
@section('content')
<div class="mx-auto max-w-2xl p-4">
    <h1 class="mb-4 font-['Space_Grotesk'] text-xl font-semibold">{{ __('Arbeitszeit-Modell') }} – {{ $user->name }}</h1>

    <form method="POST" action="{{ route('users.work-schedule.update', $user) }}"
          class="flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('Wochenstunden (Min.)') }}</span>
                <input type="number" name="weekly_minutes" min="60" max="6000" required
                       value="{{ old('weekly_minutes', $schedule->weekly_minutes) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Tagessoll (Min.)') }}</span>
                <input type="number" name="daily_target_minutes" min="30" max="720" required
                       value="{{ old('daily_target_minutes', $schedule->daily_target_minutes) }}" class="input input-bordered">
            </label>
        </div>

        <div>
            <span class="label-text">{{ __('Arbeitstage') }}</span>
            <div class="mt-1 flex flex-wrap gap-3">
                @php $days = old('working_days', (array)($schedule->working_days ?? [1,2,3,4,5])); @endphp
                @foreach([1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa',7=>'So'] as $iso => $lbl)
                    <label class="label cursor-pointer gap-1">
                        <input type="checkbox" name="working_days[]" value="{{ $iso }}" class="checkbox checkbox-xs" @checked(in_array($iso, $days))>
                        <span class="label-text">{{ $lbl }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('Kernzeit Start') }}</span>
                <input type="time" name="core_start" value="{{ old('core_start', substr((string)$schedule->core_start, 0, 5)) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Kernzeit Ende') }}</span>
                <input type="time" name="core_end" value="{{ old('core_end', substr((string)$schedule->core_end, 0, 5)) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Rahmenzeit Start') }}</span>
                <input type="time" name="frame_start" value="{{ old('frame_start', substr((string)$schedule->frame_start, 0, 5)) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Rahmenzeit Ende') }}</span>
                <input type="time" name="frame_end" value="{{ old('frame_end', substr((string)$schedule->frame_end, 0, 5)) }}" class="input input-bordered">
            </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('Pause ab Min.') }}</span>
                <input type="number" name="break_after_minutes" required
                       value="{{ old('break_after_minutes', $schedule->break_after_minutes) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Pflichtpause Min.') }}</span>
                <input type="number" name="break_minutes" required
                       value="{{ old('break_minutes', $schedule->break_minutes) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Gültig ab') }}</span>
                <input type="date" name="valid_from" required
                       value="{{ old('valid_from', optional($schedule->valid_from)->format('Y-m-d')) }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Gültig bis') }}</span>
                <input type="date" name="valid_to"
                       value="{{ old('valid_to', optional($schedule->valid_to)->format('Y-m-d')) }}" class="input input-bordered">
            </label>
        </div>

        @foreach($errors->all() as $err)
            <div class="text-sm text-error">{{ $err }}</div>
        @endforeach

        <div class="flex justify-end">
            <button class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</div>
@endsection

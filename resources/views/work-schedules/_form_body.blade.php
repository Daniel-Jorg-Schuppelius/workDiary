{{-- Shared form fields for WorkSchedule --}}

<x-form-group :legend="__('Arbeitszeit')" icon="schedule" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Wochenstunden (Min.)') }} *</label>
        <input type="number" name="weekly_minutes" min="60" max="6000" required
               value="{{ old('weekly_minutes', $schedule->weekly_minutes) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Tagessoll (Min.)') }} *</label>
        <input type="number" name="daily_target_minutes" min="30" max="720" required
               value="{{ old('daily_target_minutes', $schedule->daily_target_minutes) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <span class="fieldset-label">{{ __('Arbeitstage') }}</span>
        @php $days = old('working_days', (array) ($schedule->working_days ?? [1, 2, 3, 4, 5])); @endphp
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach ([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $iso => $lbl)
                <label class="label cursor-pointer gap-1">
                    <input type="checkbox" name="working_days[]" value="{{ $iso }}" class="checkbox checkbox-xs" @checked(in_array($iso, $days))>
                    <span class="fieldset-label">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
    </div>
</x-form-group>

<x-form-group :legend="__('Kernzeit & Rahmenzeit')" icon="schedule" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kernzeit Start') }}</label>
        <input type="time" name="core_start" value="{{ old('core_start', substr((string) $schedule->core_start, 0, 5)) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kernzeit Ende') }}</label>
        <input type="time" name="core_end" value="{{ old('core_end', substr((string) $schedule->core_end, 0, 5)) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Rahmenzeit Start') }}</label>
        <input type="time" name="frame_start" value="{{ old('frame_start', substr((string) $schedule->frame_start, 0, 5)) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Rahmenzeit Ende') }}</label>
        <input type="time" name="frame_end" value="{{ old('frame_end', substr((string) $schedule->frame_end, 0, 5)) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Pausen & Gültigkeit')" icon="restaurant" tone="success" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Pause ab Min.') }} *</label>
        <input type="number" name="break_after_minutes" required
               value="{{ old('break_after_minutes', $schedule->break_after_minutes) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Pflichtpause Min.') }} *</label>
        <input type="number" name="break_minutes" required
               value="{{ old('break_minutes', $schedule->break_minutes) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Gültig ab') }} *</label>
        <input type="date" name="valid_from" required
               value="{{ old('valid_from', optional($schedule->valid_from)->format('Y-m-d')) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Gültig bis') }}</label>
        <input type="date" name="valid_to"
               value="{{ old('valid_to', optional($schedule->valid_to)->format('Y-m-d')) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Shared form fields --}}

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Titel') }} *</span></label>
    <input type="text" name="title" class="input input-bordered @error('title') input-error @enderror"
           value="{{ old('title', $plan?->title) }}" required maxlength="255" autofocus>
    @error('title')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Zeitraum-Typ') }} *</span></label>
    <select name="period_type" class="select select-bordered @error('period_type') select-error @enderror" required>
        @foreach (\App\Models\DutyPlan::$periodTypes as $pt)
            <option value="{{ $pt }}" @selected(old('period_type', $plan?->period_type) === $pt)>
                {{ __('duty_plan.period.' . $pt) }}
            </option>
        @endforeach
    </select>
    @error('period_type')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-2 gap-4">
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Von') }} *</span></label>
        <input type="date" name="from_date" class="input input-bordered @error('from_date') input-error @enderror"
               value="{{ old('from_date', $plan?->from_date?->toDateString()) }}" required>
        @error('from_date')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="form-control">
        <label class="label"><span class="label-text">{{ __('Bis') }} *</span></label>
        <input type="date" name="to_date" class="input input-bordered @error('to_date') input-error @enderror"
               value="{{ old('to_date', $plan?->to_date?->toDateString()) }}" required>
        @error('to_date')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Mindestbesetzung pro Schicht') }}</span></label>
    <input type="number" name="min_staff" class="input input-bordered @error('min_staff') input-error @enderror"
           value="{{ old('min_staff', $plan?->min_staff ?? 0) }}" min="0" max="255">
    @error('min_staff')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Notiz') }}</span></label>
    <textarea name="note" class="textarea textarea-bordered @error('note') textarea-error @enderror" rows="3">{{ old('note', $plan?->note) }}</textarea>
    @error('note')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

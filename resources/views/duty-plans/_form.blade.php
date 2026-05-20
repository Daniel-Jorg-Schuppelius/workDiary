{{-- Shared form fields --}}

<x-form-group :legend="__('Stammdaten')" icon="assignment" tone="primary">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Titel') }} *</label>
        <input type="text" name="title" class="input input-bordered w-full @error('title') input-error @enderror"
               value="{{ old('title', $plan?->title) }}" required maxlength="255" autofocus>
        @error('title')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zeitraum-Typ') }} *</label>
        <select name="period_type" class="select select-bordered w-full @error('period_type') select-error @enderror" required>
            @foreach (\App\Enums\Shift\DutyPlanPeriodType::cases() as $pt)
                <option value="{{ $pt->value }}" @selected(old('period_type', $plan?->period_type?->value) === $pt->value)>
                    {{ $pt->label() }}
                </option>
            @endforeach
        </select>
        @error('period_type')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Zeitraum & Besetzung')" icon="event" tone="info">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zeitraum') }} *</label>
        <x-date-range
            type="date"
            fromName="from_date"
            toName="to_date"
            :fromLabel="__('Von')"
            :toLabel="__('Bis')"
            :label="false"
            :from="old('from_date', $plan?->from_date?->toDateString())"
            :to="old('to_date', $plan?->to_date?->toDateString())"
            required
            class="w-full"
        />
        @error('from_date')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
        @error('to_date')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Mindestbesetzung pro Schicht') }}</label>
        <input type="number" name="min_staff" class="input input-bordered w-full @error('min_staff') input-error @enderror"
               value="{{ old('min_staff', $plan?->min_staff ?? 0) }}" min="0" max="255">
        @error('min_staff')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Notiz')" icon="description" tone="ghost">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Notiz') }}</label>
        <textarea name="note" class="textarea textarea-bordered w-full @error('note') textarea-error @enderror" rows="3">{{ old('note', $plan?->note) }}</textarea>
        @error('note')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

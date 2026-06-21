{{-- Shared form fields --}}

<x-form-group :legend="__('Stammdaten')" icon="assignment" tone="primary">
    <x-input-field name="title" :label="__('Titel')" :value="old('title', $plan?->title)" required maxlength="255" autofocus />

    <x-select-field name="period_type" :label="__('Zeitraum-Typ')" required>
        @foreach (\App\Enums\Shift\DutyPlanPeriodType::cases() as $pt)
            <option value="{{ $pt->value }}" @selected(old('period_type', $plan?->period_type?->value) === $pt->value)>
                {{ $pt->label() }}
            </option>
        @endforeach
    </x-select-field>
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

    <x-input-field name="min_staff" type="number" :label="__('Mindestbesetzung pro Schicht')" :value="old('min_staff', $plan?->min_staff ?? 0)" min="0" max="255" />
</x-form-group>

<x-form-group :legend="__('Notiz')" icon="description" tone="ghost">
    <x-textarea-field name="note" :label="__('Notiz')" rows="3" :value="old('note', $plan?->note)" />
</x-form-group>

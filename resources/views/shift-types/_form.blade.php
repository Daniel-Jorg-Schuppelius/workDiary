<x-validation-errors />

@php $skipStatusControls = $skipStatusControls ?? false; @endphp

<x-form-group :legend="__('Stammdaten')" icon="sync" tone="primary" cols="2">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Name') }} *</div>
        <input type="text" name="name" maxlength="100" required value="{{ old('name', $type?->name) }}" class="input input-bordered w-full" autofocus />
    </label>
    @unless ($skipStatusControls)
    <div class="fieldset w-full items-end">
        <label class="fieldset-label" for="is_active">{{ __('Aktiv') }}</label>
        <input type="checkbox" id="is_active" name="is_active" value="1"
               class="toggle toggle-primary"
               @checked((bool) old('is_active', $type?->is_active ?? true))>
    </div>
    @endunless
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Kürzel') }} *</div>
        <input type="text" name="abbreviation" maxlength="5" required value="{{ old('abbreviation', $type?->abbreviation) }}" class="input input-bordered w-full" />
    </label>
    @unless ($skipStatusControls)
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Farbe') }} *</div>
        <input type="color" name="color" required value="{{ old('color', $type?->color ?? '#3b82f6') }}" class="input input-bordered w-full h-10 p-1" />
    </label>
    @endunless
</x-form-group>

<x-form-group :legend="__('Standardzeiten')" icon="schedule" tone="info">
    <x-date-range
        type="time"
        fromName="default_start_time"
        toName="default_end_time"
        :fromLabel="__('Beginn')"
        :toLabel="__('Ende')"
        :label="__('Standardzeiten')"
        :from="old('default_start_time', $type?->default_start_time)"
        :to="old('default_end_time', $type?->default_end_time)"
        formControl
    />
</x-form-group>

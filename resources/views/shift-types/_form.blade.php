@if ($errors->any())
    <div class="alert alert-error">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Name') }} *</div>
        <input type="text" name="name" maxlength="100" required value="{{ old('name', $type?->name) }}" class="input input-bordered w-full" autofocus />
    </label>
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Kürzel') }} *</div>
        <input type="text" name="abbreviation" maxlength="5" required value="{{ old('abbreviation', $type?->abbreviation) }}" class="input input-bordered w-full" />
    </label>
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Farbe') }} *</div>
        <input type="color" name="color" required value="{{ old('color', $type?->color ?? '#3b82f6') }}" class="input input-bordered w-full h-10 p-1" />
    </label>
    <label class="fieldset w-full">
        <div class="fieldset-label">{{ __('Aktiv') }}</div>
        <select name="is_active" class="select select-bordered w-full">
            <option value="1" @selected((bool) old('is_active', $type?->is_active ?? true))>{{ __('Aktiv') }}</option>
            <option value="0" @selected(! (bool) old('is_active', $type?->is_active ?? true))>{{ __('Inaktiv') }}</option>
        </select>
    </label>
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
        class="sm:col-span-2"
    />
</div>

@if ($errors->any())
    <div class="alert alert-error">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <label class="form-control">
        <span class="label-text">{{ __('Name') }} *</span>
        <input type="text" name="name" maxlength="100" required value="{{ old('name', $type?->name) }}" class="input input-bordered" autofocus />
    </label>
    <label class="form-control">
        <span class="label-text">{{ __('Kürzel') }} *</span>
        <input type="text" name="abbreviation" maxlength="5" required value="{{ old('abbreviation', $type?->abbreviation) }}" class="input input-bordered" />
    </label>
    <label class="form-control">
        <span class="label-text">{{ __('Farbe') }} *</span>
        <input type="color" name="color" required value="{{ old('color', $type?->color ?? '#3b82f6') }}" class="input input-bordered h-10 p-1" />
    </label>
    <label class="form-control">
        <span class="label-text">{{ __('Aktiv') }}</span>
        <select name="is_active" class="select select-bordered">
            <option value="1" @selected((bool) old('is_active', $type?->is_active ?? true))>{{ __('Aktiv') }}</option>
            <option value="0" @selected(! (bool) old('is_active', $type?->is_active ?? true))>{{ __('Inaktiv') }}</option>
        </select>
    </label>
    <label class="form-control">
        <span class="label-text">{{ __('Standard-Beginn') }}</span>
        <input type="time" name="default_start_time" value="{{ old('default_start_time', $type?->default_start_time) }}" class="input input-bordered" />
    </label>
    <label class="form-control">
        <span class="label-text">{{ __('Standard-Ende') }}</span>
        <input type="time" name="default_end_time" value="{{ old('default_end_time', $type?->default_end_time) }}" class="input input-bordered" />
    </label>
</div>

{{-- Shared form fields for Qualification --}}

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Name') }} *</span></label>
    <input type="text" name="name" class="input input-bordered @error('name') input-error @enderror"
           value="{{ old('name', $qualification?->name) }}" required maxlength="255" autofocus>
    @error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Kürzel') }}</span></label>
    <input type="text" name="abbreviation" class="input input-bordered @error('abbreviation') input-error @enderror"
           value="{{ old('abbreviation', $qualification?->abbreviation) }}" maxlength="20">
    @error('abbreviation')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Beschreibung') }}</span></label>
    <textarea name="description" class="textarea textarea-bordered @error('description') textarea-error @enderror" rows="3">{{ old('description', $qualification?->description) }}</textarea>
    @error('description')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label cursor-pointer gap-3">
        <span class="label-text">{{ __('Aktiv') }}</span>
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary"
               @checked(old('is_active', $qualification?->is_active ?? true))>
    </label>
</div>

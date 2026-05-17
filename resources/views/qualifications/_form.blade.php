{{-- Shared form fields for Qualification --}}

<x-form-group :legend="__('Stammdaten')" icon="school" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" class="input input-bordered w-full @error('name') input-error @enderror"
               value="{{ old('name', $qualification?->name) }}" required maxlength="255" autofocus>
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kürzel') }}</label>
        <input type="text" name="abbreviation" class="input input-bordered w-full @error('abbreviation') input-error @enderror"
               value="{{ old('abbreviation', $qualification?->abbreviation) }}" maxlength="20">
        @error('abbreviation')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Beschreibung') }}</label>
        <textarea name="description" class="textarea textarea-bordered w-full @error('description') textarea-error @enderror" rows="3">{{ old('description', $qualification?->description) }}</textarea>
        @error('description')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer gap-3">
            <span class="fieldset-label">{{ __('Aktiv') }}</span>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary"
                   @checked(old('is_active', $qualification?->is_active ?? true))>
        </label>
    </div>
</x-form-group>

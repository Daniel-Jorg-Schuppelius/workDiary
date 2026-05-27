@php
    /** @var \App\Models\Software $software */
    /** @var array<string, string> $kindOptions */
    /** @var array<string, string> $licenseTypeOptions */
    $skipStatusControls = $skipStatusControls ?? false;
    $currentKind = old('kind', $software->kind?->value);
    $currentLicense = old('license_type', $software->license_type?->value);
@endphp

<x-form-group :legend="__('Stammdaten')" icon="apps" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required value="{{ old('name', $software->name) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Hersteller') }}</label>
        <input type="text" name="vendor" value="{{ old('vendor', $software->vendor) }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Standardversion') }}</label>
        <input type="text" name="default_version" value="{{ old('default_version', $software->default_version) }}" class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Art & Lizenz')" icon="verified" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Art') }} *</label>
        <select name="kind" class="select select-bordered w-full" required>
            @foreach ($kindOptions as $value => $label)
                <option value="{{ $value }}" @selected($currentKind === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Lizenztyp') }} *</label>
        <select name="license_type" class="select select-bordered w-full" required>
            @foreach ($licenseTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected($currentLicense === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @unless ($skipStatusControls)
        <div class="fieldset md:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked(old('is_active', (bool) ($software->is_active ?? true)))>
                <span class="fieldset-label">{{ __('Aktiv') }}</span>
            </label>
        </div>
    @endunless
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="sticky_note_2" cols="1">
    <div class="fieldset md:col-span-2">
        <textarea name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes', $software->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

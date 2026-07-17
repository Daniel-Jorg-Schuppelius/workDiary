@php
    /** @var \App\Models\Software $software */
    /** @var array<string, string> $kindOptions */
    /** @var array<string, string> $licenseTypeOptions */
    $skipStatusControls = $skipStatusControls ?? false;
    $currentKind = old('kind', $software->kind?->value);
    $currentLicense = old('license_type', $software->license_type?->value);
@endphp

<x-form-group :legend="__('Stammdaten')" icon="apps" tone="primary" cols="2">
    <x-input-field name="name" :label="__('Name')" required :value="old('name', $software->name)" :span="2" />
    <x-input-field name="vendor" :label="__('Hersteller')" :value="old('vendor', $software->vendor)" />
    <x-input-field name="default_version" :label="__('Standardversion')" :value="old('default_version', $software->default_version)" />
</x-form-group>

<x-form-group :legend="__('Art & Lizenz')" icon="verified" tone="info" cols="2">
    <x-select-field name="kind" :label="__('Art')" required>
        @foreach ($kindOptions as $value => $label)
            <option value="{{ $value }}" @selected($currentKind === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="license_type" :label="__('Lizenztyp')" required>
        @foreach ($licenseTypeOptions as $value => $label)
            <option value="{{ $value }}" @selected($currentLicense === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>
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
    <x-textarea-field name="notes" rows="3" :value="old('notes', $software->notes)" :span="2" />
</x-form-group>

<x-validation-errors />

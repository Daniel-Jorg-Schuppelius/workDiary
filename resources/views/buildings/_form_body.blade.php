{{-- Shared form fields for Building (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Building|null $building
     * @var \Illuminate\Support\Collection<int, \App\Models\Site> $sites
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="apartment" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Standort') }} *</label>
        <select name="site_id" required class="select select-bordered w-full @error('site_id') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach ($sites as $s)
                <option value="{{ $s->sqid }}" @selected((string) old('site_id', \App\Support\Sqid::encode(\App\Models\Site::class, $building?->site_id ?? \App\Support\Sqid::decode(\App\Models\Site::class, request('site'))) ) === $s->sqid)>{{ $s->name }}@if ($s->customer) — {{ $s->customer->name }}@endif</option>
            @endforeach
        </select>
        @error('site_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Name') }} *</label>
        <input type="text" name="name" required maxlength="160" autofocus
               value="{{ old('name', $building?->name) }}"
               class="input input-bordered w-full @error('name') input-error @enderror">
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Code') }}</label>
        <input type="text" name="code" maxlength="32"
               value="{{ old('code', $building?->code) }}"
               class="input input-bordered w-full @error('code') input-error @enderror">
        @error('code')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Kennzahlen')" icon="straighten" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Bruttogrundfläche (m²)') }}</label>
        <input type="number" step="0.01" min="0" name="gross_area_m2"
               value="{{ old('gross_area_m2', $building?->gross_area_m2) }}"
               class="input input-bordered w-full @error('gross_area_m2') input-error @enderror">
        @error('gross_area_m2')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Baujahr') }}</label>
        <input type="number" min="1800" max="2999" name="year_built"
               value="{{ old('year_built', $building?->year_built) }}"
               class="input input-bordered w-full @error('year_built') input-error @enderror">
        @error('year_built')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <div class="fieldset">
        <textarea name="notes" rows="3" maxlength="2000"
                  class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror">{{ old('notes', $building?->notes) }}</textarea>
        @error('notes')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

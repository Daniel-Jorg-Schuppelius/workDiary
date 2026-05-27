{{-- Shared form fields for Floor (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Floor|null $floor
     * @var \Illuminate\Support\Collection<int, \App\Models\Building> $buildings
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="layers" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Gebäude') }} *</label>
        <select name="building_id" required class="select select-bordered w-full @error('building_id') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach ($buildings as $b)
                <option value="{{ $b->id }}" @selected((int) old('building_id', $floor?->building_id ?? request('building')) === (int) $b->id)>{{ $b->name }}@if ($b->site) — {{ $b->site->name }}@endif</option>
            @endforeach
        </select>
        @error('building_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ebene') }} *</label>
        <input type="number" min="-10" max="200" name="level" required
               value="{{ old('level', $floor?->level) }}"
               class="input input-bordered w-full @error('level') input-error @enderror">
        @error('level')<p class="text-error text-sm">{{ $message }}</p>@enderror
        <p class="text-xs text-base-content/60 mt-1">{{ __('0 = Erdgeschoss, negativ = Untergeschoss.') }}</p>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Bezeichnung') }} *</label>
        <input type="text" name="label" required maxlength="80" autofocus
               value="{{ old('label', $floor?->label) }}"
               class="input input-bordered w-full @error('label') input-error @enderror">
        @error('label')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Bruttogrundfläche (m²)') }}</label>
        <input type="number" step="0.01" min="0" name="gross_area_m2"
               value="{{ old('gross_area_m2', $floor?->gross_area_m2) }}"
               class="input input-bordered w-full @error('gross_area_m2') input-error @enderror">
        @error('gross_area_m2')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <div class="fieldset">
        <textarea name="notes" rows="3" maxlength="2000"
                  class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror">{{ old('notes', $floor?->notes) }}</textarea>
        @error('notes')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

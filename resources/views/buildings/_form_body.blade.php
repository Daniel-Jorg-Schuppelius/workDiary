{{-- Shared form fields for Building (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Building|null $building
     * @var \Illuminate\Support\Collection<int, \App\Models\Site> $sites
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="apartment" tone="primary" cols="2">
    <x-select-field name="site_id" :label="__('Standort')" required span="2">
        <option value="">{{ __('— bitte wählen —') }}</option>
        @foreach ($sites as $s)
            <option value="{{ $s->sqid }}" @selected((string) old('site_id', \App\Support\Sqid::encode(\App\Models\Site::class, $building?->site_id ?? \App\Support\Sqid::decode(\App\Models\Site::class, request('site'))) ) === $s->sqid)>{{ $s->name }}@if ($s->customer) — {{ $s->customer->name }}@endif</option>
        @endforeach
    </x-select-field>
    <x-input-field name="name" :label="__('Name')" required maxlength="160" autofocus :value="old('name', $building?->name)" />
    <x-input-field name="code" :label="__('Code')" maxlength="32" :value="old('code', $building?->code)" />
</x-form-group>

<x-form-group :legend="__('Kennzahlen')" icon="straighten" tone="info" cols="2">
    <x-input-field name="gross_area_m2" type="number" :label="__('Bruttogrundfläche (m²)')" step="0.01" min="0" :value="old('gross_area_m2', $building?->gross_area_m2)" />
    <x-input-field name="year_built" type="number" :label="__('Baujahr')" min="1800" max="2999" :value="old('year_built', $building?->year_built)" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <x-textarea-field name="notes" rows="3" maxlength="2000" :value="old('notes', $building?->notes)" />
</x-form-group>

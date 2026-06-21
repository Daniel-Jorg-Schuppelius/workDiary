{{-- Shared form fields for Floor (used by _form_dialog) --}}
@php
    /**
     * @var \App\Models\Floor|null $floor
     * @var \Illuminate\Support\Collection<int, \App\Models\Building> $buildings
     */
@endphp

<x-form-group :legend="__('Stammdaten')" icon="layers" tone="primary" cols="2">
    <x-select-field name="building_id" :label="__('Gebäude')" required span="2">
        <option value="">{{ __('— bitte wählen —') }}</option>
        @foreach ($buildings as $b)
            <option value="{{ $b->sqid }}" @selected((string) old('building_id', \App\Support\Sqid::encode(\App\Models\Building::class, $floor?->building_id ?? \App\Support\Sqid::decode(\App\Models\Building::class, request('building'))) ) === $b->sqid)>{{ $b->name }}@if ($b->site) — {{ $b->site->name }}@endif</option>
        @endforeach
    </x-select-field>
    <x-input-field name="level" type="number" :label="__('Ebene')" required min="-10" max="200" :value="old('level', $floor?->level)" :hint="__('0 = Erdgeschoss, negativ = Untergeschoss.')" />
    <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="80" autofocus :value="old('label', $floor?->label)" />
    <x-input-field name="gross_area_m2" type="number" :label="__('Bruttogrundfläche (m²)')" span="2" step="0.01" min="0" :value="old('gross_area_m2', $floor?->gross_area_m2)" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
    <x-textarea-field name="notes" :label="null" rows="3" maxlength="2000" :value="old('notes', $floor?->notes)" />
</x-form-group>

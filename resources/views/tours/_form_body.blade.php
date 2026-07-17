{{-- Shared form fields for Tour (create only). The Edit-Page (tours.edit) ist ein
     separater Planungs-/Optimierungs-Editor mit Auftragszuweisung und Karte. --}}

<x-form-group :legend="__('Stammdaten')" icon="map" tone="primary" cols="2">
    <x-input-field name="tour_date" type="date" :label="__('Datum')" required :value="old('tour_date', $date)" />
    <x-select-field name="user_id" :label="__('Fahrer')" required>
        @foreach ($users as $u)
            <option value="{{ $u->sqid }}" @selected((string) old('user_id') === $u->sqid)>{{ $u->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="vehicle_id" :label="__('Fahrzeug')">
        <option value="">—</option>
        @foreach ($vehicles as $v)
            <option value="{{ $v->sqid }}">{{ $v->license_plate }} {{ $v->label }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="name" :label="__('Name')" maxlength="200" :value="old('name')" />
</x-form-group>

<x-validation-errors />

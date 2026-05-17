{{-- Shared form fields for Tour (create only). The Edit-Page (tours.edit) ist ein
     separater Planungs-/Optimierungs-Editor mit Auftragszuweisung und Karte. --}}

<x-form-group :legend="__('Stammdaten')" icon="map" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="tour_date" required value="{{ old('tour_date', $date) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Fahrer') }} *</label>
        <select name="user_id" required class="select select-bordered w-full">
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected((int) old('user_id') === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Fahrzeug') }}</label>
        <select name="vehicle_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($vehicles as $v)
                <option value="{{ $v->id }}">{{ $v->license_plate }} {{ $v->label }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Name') }}</label>
        <input type="text" name="name" maxlength="200"
               value="{{ old('name') }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

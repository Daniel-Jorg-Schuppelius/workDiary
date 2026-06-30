{{-- Shared form fields for CustomerGeofence (used by _form_dialog) --}}
@php
    use App\Models\{Customer, Project, Site};
    use App\Support\Sqid;

    /**
     * @var \App\Models\Location\CustomerGeofence|null $geofence
     * @var \Illuminate\Support\Collection<int, \App\Models\Customer> $customers
     * @var \Illuminate\Support\Collection<int, \App\Models\Site> $sites
     * @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects
     */
    $defaults = (array) config('location.defaults', []);
    $selectedCustomer = old('customer_id', Sqid::encodeOrNull(Customer::class,
        $geofence?->customer_id ?? Sqid::decodeOrNumeric(Customer::class, request('customer'))));
    $selectedSite = old('site_id', Sqid::encodeOrNull(Site::class, $geofence?->site_id));
    $selectedProject = old('project_id', Sqid::encodeOrNull(Project::class, $geofence?->project_id));
@endphp

<x-form-group :legend="__('Zuordnung')" icon="pin_drop" tone="primary" cols="2">
    <x-input-field name="customer_id" :label="__('Kunde')" required>
        <select name="customer_id" required class="select select-bordered w-full @error('customer_id') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) $selectedCustomer === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
    </x-input-field>
    <x-input-field name="label" :label="__('Bezeichnung')" required :value="old('label', $geofence?->label)" maxlength="160" autofocus />
    <x-input-field name="site_id" :label="__('Standort (optional)')">
        <select name="site_id" class="select select-bordered w-full @error('site_id') select-error @enderror">
            <option value="">{{ __('— keiner —') }}</option>
            @foreach ($sites as $s)
                <option value="{{ $s->sqid }}" @selected((string) $selectedSite === $s->sqid)>{{ $s->name }}</option>
            @endforeach
        </select>
    </x-input-field>
    <x-input-field name="project_id" :label="__('Zielprojekt (optional)')">
        <select name="project_id" class="select select-bordered w-full @error('project_id') select-error @enderror">
            <option value="">{{ __('— Standardprojekt des Kunden —') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" @selected((string) $selectedProject === $p->sqid)>{{ $p->name }}</option>
            @endforeach
        </select>
    </x-input-field>
</x-form-group>

<x-form-group :legend="__('Geo-Zone')" icon="my_location" tone="info" cols="2">
    <x-input-field name="center_lat" type="number" :label="__('Breitengrad')" step="0.0000001" min="-90" max="90" required :value="old('center_lat', $geofence?->center_lat)" />
    <x-input-field name="center_lng" type="number" :label="__('Längengrad')" step="0.0000001" min="-180" max="180" required :value="old('center_lng', $geofence?->center_lng)" />
    <x-input-field name="radius_m" type="number" :label="__('Radius (m)')" min="10" max="5000" required :value="old('radius_m', $geofence?->radius_m ?? ($defaults['radius_m'] ?? 100))" />
</x-form-group>

<x-form-group :legend="__('Erfassungsregeln')" icon="schedule" tone="ghost" cols="2">
    <x-input-field name="min_dwell_minutes" type="number" :label="__('Mindest-Verweildauer (min)')" min="0" max="1440" required
        :value="old('min_dwell_minutes', $geofence?->min_dwell_minutes ?? ($defaults['min_dwell_minutes'] ?? 5))"
        :hint="__('Kürzere Aufenthalte gelten als Durchfahrt.')" />
    <x-input-field name="gap_merge_minutes" type="number" :label="__('Lücken-Toleranz (min)')" min="0" max="1440" required
        :value="old('gap_merge_minutes', $geofence?->gap_merge_minutes ?? ($defaults['gap_merge_minutes'] ?? 10))"
        :hint="__('Kurze Aussetzer beenden den Aufenthalt nicht.')" />
    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer gap-3 justify-start">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary"
                   @checked(old('is_active', $geofence?->is_active ?? true))>
            <span class="fieldset-label">{{ __('Aktiv') }}</span>
        </label>
    </div>
</x-form-group>

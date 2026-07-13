{{--
  Hierarchischer Liegenschafts-Picker (Customer → Site → Building → Floor → Room).
  Alle Selects sind Alpine-gefiltert. Die per `name` gerenderten Felder werden
  serverseitig validiert; die Hierarchie-Selects ohne Persistenz-Feld dienen nur
  zur Eingrenzung.

  Props:
    - customers:    Collection<Customer>    — alle wählbaren Kunden
    - sites:        Collection<Site>        — alle Sites (mit customer_id)
    - buildings:    Collection<Building>    — alle Buildings (mit site_id)
    - floors:       Collection<Floor>       — alle Floors (mit building_id)
    - rooms:        Collection<Room>|null   — optional, alle Räume (mit floor_id, customer_id)
    - selected:     array{customer_id?:?int,site_id?:?int,building_id?:?int,floor_id?:?int,room_id?:?int}
    - withRoom:     bool   default false
    - withCustomer: bool   default true
    - withFloor:    bool   default true
    - requireFloor: bool   default false
    - requireRoom:  bool   default false
    - floorName:    string default 'floor_id'    — Name des persistierten Felds
    - roomName:     string default 'room_id'
    - customerName: string default 'customer_id'
--}}
@props([
    'customers' => collect(),
    'sites' => collect(),
    'buildings' => collect(),
    'floors' => collect(),
    'rooms' => null,
    'selected' => [],
    'withRoom' => false,
    'withCustomer' => true,
    'withFloor' => true,
    'requireFloor' => false,
    'requireRoom' => false,
    'floorName' => 'floor_id',
    'roomName' => 'room_id',
    'customerName' => 'customer_id',
    'withForeignCustomer' => false,
    'foreignCustomers' => null,
    'foreignCustomerName' => 'foreign_customer_id',
])

@php
    $selectedCustomerId = old($customerName, $selected['customer_id'] ?? null);
    $selectedForeignCustomerId = old($foreignCustomerName, $selected['foreign_customer_id'] ?? null);
    $selectedSiteId     = old('_picker_site_id', $selected['site_id'] ?? null);
    $selectedBuildingId = old('_picker_building_id', $selected['building_id'] ?? null);
    $selectedFloorId    = old($floorName, $selected['floor_id'] ?? null);
    $selectedRoomId     = old($roomName, $selected['room_id'] ?? null);

    $pickerData = [
        'customers' => $customers->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])->values(),
        'foreignCustomers' => ($foreignCustomers ?? collect())->map(fn ($fc) => [
            'id' => (int) $fc->id,
            'name' => (string) $fc->name,
            'customer_id' => $fc->customer_id !== null ? (int) $fc->customer_id : null,
        ])->values(),
        'sites'     => $sites->map(fn ($s) => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'customer_id' => $s->customer_id !== null ? (int) $s->customer_id : null,
        ])->values(),
        'buildings' => $buildings->map(fn ($b) => [
            'id' => (int) $b->id,
            'name' => (string) $b->name,
            'site_id' => $b->site_id !== null ? (int) $b->site_id : null,
        ])->values(),
        'floors'    => $floors->map(fn ($f) => [
            'id' => (int) $f->id,
            'label' => (string) $f->label,
            'level' => (int) $f->level,
            'building_id' => $f->building_id !== null ? (int) $f->building_id : null,
        ])->values(),
        'rooms'     => ($rooms ?? collect())->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => (string) $r->name,
            'floor_id' => $r->floor_id !== null ? (int) $r->floor_id : null,
            'customer_id' => $r->customer_id !== null ? (int) $r->customer_id : null,
        ])->values(),
    ];

    $pickerConfig = [
        'data' => $pickerData,
        'initial' => [
            'customer_id' => $selectedCustomerId !== null && $selectedCustomerId !== '' ? (int) $selectedCustomerId : null,
            'foreign_customer_id' => $selectedForeignCustomerId !== null && $selectedForeignCustomerId !== '' ? (int) $selectedForeignCustomerId : null,
            'site_id' => $selectedSiteId !== null && $selectedSiteId !== '' ? (int) $selectedSiteId : null,
            'building_id' => $selectedBuildingId !== null && $selectedBuildingId !== '' ? (int) $selectedBuildingId : null,
            'floor_id' => $selectedFloorId !== null && $selectedFloorId !== '' ? (int) $selectedFloorId : null,
            'room_id' => $selectedRoomId !== null && $selectedRoomId !== '' ? (int) $selectedRoomId : null,
        ],
        'withRoom' => (bool) $withRoom,
        'withForeignCustomer' => (bool) $withForeignCustomer,
    ];
@endphp

<div
    x-data="facilityPicker"
    data-config="{{ json_encode($pickerConfig) }}"
    class="contents"
>
    @if ($withCustomer)
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Kunde') }}</label>
            <select name="{{ $customerName }}" x-model.number="customer_id" @change="onCustomerChange()"
                    class="select select-bordered w-full @error($customerName) select-error @enderror">
                <option :value="null">{{ __('— ohne Kunde —') }}</option>
                <template x-for="c in data.customers" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
            @error($customerName)<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        @if ($withForeignCustomer)
            <div class="fieldset" x-show="hasForeignCustomers" x-cloak>
                <label class="fieldset-label">{{ __('Fremdkunde') }}</label>
                <select name="{{ $foreignCustomerName }}" x-model.number="foreign_customer_id"
                        class="select select-bordered w-full @error($foreignCustomerName) select-error @enderror">
                    <option :value="null">{{ __('— ohne Fremdkunde —') }}</option>
                    <template x-for="fc in filteredForeignCustomers" :key="fc.id">
                        <option :value="fc.id" x-text="fc.name"></option>
                    </template>
                </select>
                @error($foreignCustomerName)<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        @endif
    @endif

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Standort') }}</label>
        <select name="_picker_site_id" x-model.number="site_id" @change="onSiteChange()"
                class="select select-bordered w-full">
            <option :value="null">{{ __('— bitte wählen —') }}</option>
            <template x-for="s in filteredSites" :key="s.id">
                <option :value="s.id" x-text="s.name"></option>
            </template>
        </select>
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Gebäude') }}</label>
        <select name="_picker_building_id" x-model.number="building_id" @change="onBuildingChange()"
                class="select select-bordered w-full">
            <option :value="null">{{ __('— bitte wählen —') }}</option>
            <template x-for="b in filteredBuildings" :key="b.id">
                <option :value="b.id" x-text="b.name"></option>
            </template>
        </select>
    </div>

    @if ($withFloor)
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Geschoss') }}@if ($requireFloor) *@endif</label>
            <select name="{{ $floorName }}" x-model.number="floor_id" @change="onFloorChange()" @if ($requireFloor) required @endif
                    class="select select-bordered w-full @error($floorName) select-error @enderror">
                <option :value="null">{{ __('— bitte wählen —') }}</option>
                <template x-for="f in filteredFloors" :key="f.id">
                    <option :value="f.id" x-text="floorLabel(f)"></option>
                </template>
            </select>
            @error($floorName)<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    @endif

    @if ($withRoom)
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Raum') }}@if ($requireRoom) *@endif</label>
            <select name="{{ $roomName }}" x-model.number="room_id" @if ($requireRoom) required @endif
                    class="select select-bordered w-full @error($roomName) select-error @enderror">
                <option :value="null">{{ __('— ohne Raum —') }}</option>
                <template x-for="r in filteredRooms" :key="r.id">
                    <option :value="r.id" x-text="r.name"></option>
                </template>
            </select>
            @error($roomName)<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    @endif
</div>

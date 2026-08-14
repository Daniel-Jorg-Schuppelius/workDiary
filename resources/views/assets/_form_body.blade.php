{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Asset $asset */
    /** @var array<int|string, string> $customers */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ForeignCustomer> $foreignCustomers */
    /** @var array<string, string> $classOptions */
    /** @var array<string, string> $statusOptions */
    /** @var array<string, string> $categoryOptions */
    /** @var array{customer_id:?int, foreign_customer_id:?int, site_id:?int, building_id:?int, floor_id:?int, room_id:?int} $prefill */
    $asset = $asset ?? new \App\Models\Asset();
    $prefill = $prefill ?? ['customer_id' => null, 'foreign_customer_id' => null, 'site_id' => null, 'building_id' => null, 'floor_id' => null, 'room_id' => null];
    $pickerCustomers = collect($customers)
        ->map(fn ($name, $id) => (object) ['id' => (int) $id, 'name' => (string) $name])
        ->values();
@endphp

<x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
    <x-select-field name="asset_class" :label="__('Typ')" required>
        @foreach ($classOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('asset_class', $asset->asset_class?->value ?? $asset->asset_class) === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    <x-select-field name="status" :label="__('Status')" required>
        @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $asset->status?->value ?? $asset->status ?? \App\Enums\Asset\AssetStatus::Active->value) === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="name" :label="__('Name')" required span="2" :value="old('name', $asset->name)" />

    <x-select-field name="category_code" :label="__('Kategorie')">
        <option value="">{{ __('— ohne Kategorie —') }}</option>
        @foreach ($categoryOptions as $code => $label)
            <option value="{{ $code }}" @selected(old('category_code', $asset->category_code) === $code)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="inventory_no" :label="__('Inventarnummer')" :value="old('inventory_no', $asset->inventory_no)" />

    <x-select-field name="product_id" :label="__('products.field.product')" span="2" :hint="__('products.field.product_help')">
        <option value="">{{ __('products.field.no_product') }}</option>
        @foreach ($products ?? [] as $productOption)
            <option value="{{ $productOption->sqid }}" @selected((string) old('product_id', \App\Support\Sqid::encode(\App\Models\Product::class, $asset->product_id ?? null)) === $productOption->sqid)>{{ $productOption->name }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="manufacturer" :label="__('Hersteller')" :value="old('manufacturer', $asset->manufacturer)" />

    <x-input-field name="model" :label="__('Modell')" :value="old('model', $asset->model)" />

    <x-input-field name="serial_no" :label="__('Seriennummer')" :value="old('serial_no', $asset->serial_no)" />

    <x-input-field name="location_text" :label="__('Standort (Freitext)')" span="2" :value="old('location_text', $asset->location_text)" />
</x-form-group>

<x-form-group :legend="__('Verortung')" icon="location_on" tone="info" cols="2">
    <x-facility-picker
        with-room
        with-foreign-customer
        :customers="$pickerCustomers"
        :foreign-customers="$foreignCustomers ?? collect()"
        :sites="$sites ?? collect()"
        :buildings="$buildings ?? collect()"
        :floors="$floors ?? collect()"
        :rooms="$rooms ?? collect()"
        :selected="$prefill" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="sticky_note_2" cols="1">
    <x-textarea-field name="notes" rows="3" span="2" :value="old('notes', $asset->notes)" />
</x-form-group>

@php
    $allTags = $allTags ?? collect();
    $selectedTagIds = old('tag_ids', $asset->exists ? $asset->tags->map(fn ($t) => $t->sqid)->all() : []);
@endphp
<x-form-group :legend="__('Tags')" icon="sell" tone="ghost" cols="1">
    <div class="fieldset md:col-span-2">
        <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" />
    </div>
</x-form-group>

<x-validation-errors />

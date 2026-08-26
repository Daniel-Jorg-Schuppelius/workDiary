{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $warehouse (Warehouse|null), $isDialog, $sites/$vehicles/$teams (Sqid → Label) --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $warehouse ? route('warehouses.update', $warehouse) : route('warehouses.store');
    $dialogUrl = ($warehouse ? route('warehouses.edit', $warehouse) : route('warehouses.create')) . '?dialog=1';
    $kind = old('kind', $warehouse?->kind?->value ?? \App\Enums\Inventory\WarehouseKind::Fixed->value);
    // Bezug (MVP-706): Sqid-Werte im Formular, Dekodierung im Request (DecodesSqidInputs).
    $references = [
        'site' => ['field' => 'site_id', 'model' => \App\Models\Site::class, 'options' => $sites ?? [], 'label' => __('inventory.field.site')],
        'vehicle' => ['field' => 'vehicle_id', 'model' => \App\Models\Vehicle::class, 'options' => $vehicles ?? [], 'label' => __('inventory.field.vehicle')],
        'team' => ['field' => 'team_id', 'model' => \App\Models\Team::class, 'options' => $teams ?? [], 'label' => __('inventory.field.team')],
    ];
@endphp

<x-modal
    :title="$warehouse ? __('inventory.action.edit_warehouse') : __('inventory.action.create_warehouse')"
    :eyebrow="__('inventory.warehouses')"
    icon="warehouse"
    tone="primary"
    :action="$action"
    :method="$warehouse ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$warehouse ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="warehouse" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="255" :value="old('name', $warehouse?->name)" />
        <x-input-field name="code" :label="__('inventory.field.code')" maxlength="40" :value="old('code', $warehouse?->code)" />
        <x-input-field name="location_note" :label="__('inventory.field.location_note')" maxlength="255" :value="old('location_note', $warehouse?->location_note)" :span="2" />
    </x-form-group>

    {{-- Art + bedingter Bezug: Logik in Alpine.data('reveal') (CSP-konform), die Direktiven rufen nur Methoden. --}}
    <div x-data="reveal(@js($kind))">
        <x-form-group :legend="__('inventory.field.kind')" icon="category" tone="primary" cols="2">
            <x-select-field name="kind" :label="__('inventory.field.kind')" required x-model="value">
                @foreach (\App\Enums\Inventory\WarehouseKind::cases() as $case)
                    <option value="{{ $case->value }}" @selected($kind === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-select-field>
            @foreach ($references as $key => $ref)
                @php $selected = (string) old($ref['field'], \App\Support\Sqid::encode($ref['model'], $warehouse?->{$ref['field']})); @endphp
                <div x-show="is('{{ $key }}')" x-cloak>
                    <x-select-field :name="$ref['field']" :label="$ref['label']">
                        <option value="">—</option>
                        @foreach ($ref['options'] as $sqid => $label)
                            <option value="{{ $sqid }}" @selected($selected === $sqid)>{{ $label }}</option>
                        @endforeach
                    </x-select-field>
                </div>
            @endforeach
        </x-form-group>
    </div>

    <x-form-group :legend="__('Status')" icon="tune" tone="primary" cols="3">
        @foreach (['is_default' => __('inventory.field.default'), 'active' => __('article.status.active'), 'blocked' => __('inventory.state.blocked')] as $key => $label)
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1" class="checkbox checkbox-sm"
                       @checked(old($key, $warehouse ? $warehouse->{$key} : ($key === 'active')))>
                <span class="label-text">{{ $label }}</span>
            </label>
        @endforeach
    </x-form-group>
</x-modal>

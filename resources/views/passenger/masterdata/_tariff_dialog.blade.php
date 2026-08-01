{{-- Dialog: Tarif anlegen/bearbeiten (MVP-456) — versioniert über Gültigkeitszeitraum. --}}
@php $editing = $tariff !== null; @endphp
<x-modal
    :title="$editing ? __('passenger.masterdata.action.edit_tariff') : __('passenger.masterdata.action.create_tariff')"
    :eyebrow="__('passenger.masterdata.tariffs')"
    icon="price_change"
    tone="primary"
    :action="$editing ? route('passenger-masterdata.tariffs.update', $tariff) : route('passenger-masterdata.tariffs.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('passenger.masterdata.action.save')"
>
    <x-form-group :legend="__('passenger.section.tariff_base')" icon="price_change" tone="primary" cols="2">
        <x-input-field name="name" :label="__('passenger.field.name')" :value="old('name', $tariff?->name)" required span="2" />
        <x-input-field name="tariff_area" :label="__('passenger.field.tariff_area')" :value="old('tariff_area', $tariff?->tariff_area)" />
        <x-select-field name="operation_mode" :label="__('passenger.field.operation_mode')" required>
            @foreach (\App\Enums\Passenger\RideOperationMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('operation_mode', $tariff?->operation_mode->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="valid_from" type="date" :label="__('passenger.field.valid_from')" :value="old('valid_from', $tariff?->valid_from?->toDateString())" required />
        <x-input-field name="valid_until" type="date" :label="__('passenger.field.valid_until')" :value="old('valid_until', $tariff?->valid_until?->toDateString())" />
    </x-form-group>

    <x-form-group :legend="__('passenger.section.tariff_prices')" icon="payments" tone="primary" cols="3">
        <x-input-field name="base_price" type="number" step="0.0001" min="0" :label="__('passenger.field.base_price')" :value="old('base_price', $tariff?->base_price)" required />
        <x-input-field name="price_per_km" type="number" step="0.0001" min="0" :label="__('passenger.field.price_per_km')" :value="old('price_per_km', $tariff?->price_per_km)" required />
        <x-input-field name="price_per_minute" type="number" step="0.0001" min="0" :label="__('passenger.field.price_per_minute')" :value="old('price_per_minute', $tariff?->price_per_minute)" required />
        <x-input-field name="min_price" type="number" step="0.0001" min="0" :label="__('passenger.field.min_price')" :value="old('min_price', $tariff?->min_price)" />
        <x-input-field name="fixed_price_min_percent" type="number" step="0.001" min="0" :label="__('passenger.field.fixed_price_min_percent')" :value="old('fixed_price_min_percent', $tariff?->fixed_price_min_percent)" :hint="__('passenger.hint.fixed_price_corridor')" />
        <x-input-field name="fixed_price_max_percent" type="number" step="0.001" min="0" :label="__('passenger.field.fixed_price_max_percent')" :value="old('fixed_price_max_percent', $tariff?->fixed_price_max_percent)" />
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="tariff-active" name="active" value="1" class="checkbox checkbox-sm" @checked((bool) old('active', $tariff?->active ?? true))>
            <label for="tariff-active" class="text-sm">{{ __('passenger.badge.active') }}</label>
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>

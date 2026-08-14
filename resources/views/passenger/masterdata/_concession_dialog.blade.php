{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _concession_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Konzession anlegen/bearbeiten (MVP-456) — je Betriebsart mit Gültigkeit. --}}
@php $editing = $concession !== null; @endphp
<x-modal
    :title="$editing ? __('passenger.masterdata.action.edit_concession') : __('passenger.masterdata.action.create_concession')"
    :eyebrow="__('passenger.masterdata.concessions')"
    icon="verified"
    tone="primary"
    :action="$editing ? route('passenger-masterdata.concessions.update', $concession) : route('passenger-masterdata.concessions.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('passenger.masterdata.action.save')"
>
    <x-form-group :legend="__('passenger.masterdata.concessions')" icon="verified" tone="primary" cols="2">
        <x-select-field name="operation_mode" :label="__('passenger.field.operation_mode')" required>
            @foreach (\App\Enums\Passenger\RideOperationMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('operation_mode', $concession?->operation_mode->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="authority" :label="__('passenger.field.authority')" :value="old('authority', $concession?->authority)" required />
        <x-input-field name="reference_no" :label="__('passenger.field.reference_no')" :value="old('reference_no', $concession?->reference_no)" required />
        <x-input-field name="licensed_vehicles" type="number" min="0" :label="__('passenger.field.licensed_vehicles')" :value="old('licensed_vehicles', $concession?->licensed_vehicles)" />
        <x-input-field name="business_seat" :label="__('passenger.field.business_seat')" :value="old('business_seat', $concession?->business_seat)" />
        <x-input-field name="service_area" :label="__('passenger.field.service_area')" :value="old('service_area', $concession?->service_area)" />
        <x-input-field name="tariff_area" :label="__('passenger.field.tariff_area')" :value="old('tariff_area', $concession?->tariff_area)" span="2" />
        <x-input-field name="valid_from" type="date" :label="__('passenger.field.valid_from')" :value="old('valid_from', $concession?->valid_from?->toDateString())" />
        <x-input-field name="valid_until" type="date" :label="__('passenger.field.valid_until')" :value="old('valid_until', $concession?->valid_until?->toDateString())" />
        <x-textarea-field name="conditions" :label="__('passenger.field.conditions')" rows="2" span="2">{{ old('conditions', $concession?->conditions) }}</x-textarea-field>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="concession-active" name="active" value="1" class="checkbox checkbox-sm" @checked((bool) old('active', $concession?->active ?? true))>
            <label for="concession-active" class="text-sm">{{ __('passenger.badge.active') }}</label>
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>

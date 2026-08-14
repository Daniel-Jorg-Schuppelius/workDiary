{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Schichtabrechnung anlegen/bearbeiten (MVP-456, Konzept §8). --}}
@php $editing = $settlement !== null; @endphp
<x-modal
    :title="$editing ? __('passenger.settlements.action.edit') : __('passenger.settlements.action.create')"
    :eyebrow="__('passenger.settlements.title')"
    icon="payments"
    tone="primary"
    :action="$editing ? route('passenger-settlements.update', $settlement) : route('passenger-settlements.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('passenger.masterdata.action.save')"
>
    <x-form-group :legend="__('passenger.section.shift')" icon="badge" tone="primary" cols="2">
        @if ($editing)
            <input type="hidden" name="driver_user_id" value="{{ $settlement->driver?->sqid }}">
            <input type="hidden" name="shift_date" value="{{ $settlement->shift_date->toDateString() }}">
            <x-input-field name="driver_display" :label="__('passenger.field.driver')" :value="$settlement->driver->name ?? '—'" disabled />
            <x-input-field name="shift_date_display" :label="__('passenger.field.shift_date')" :value="$settlement->shift_date->fdate()" disabled />
        @else
            <div>
                <label class="label"><span class="label-text">{{ __('passenger.field.driver') }} *</span></label>
                <x-user-select name="driver_user_id" :users="$drivers" value-key="sqid" :selected="old('driver_user_id')" required />
            </div>
            <x-input-field name="shift_date" type="date" :label="__('passenger.field.shift_date')" :value="old('shift_date', now()->toDateString())" required />
        @endif
        <x-select-field name="vehicle_id" :label="__('passenger.field.vehicle')">
            <option value="">—</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->sqid }}" @selected((string) old('vehicle_id', $settlement?->vehicle?->sqid) === $vehicle->sqid)>{{ $vehicle->license_plate }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('passenger.section.revenue')" icon="payments" tone="primary" cols="3">
        <x-input-field name="meter_total" type="number" step="0.01" min="0" :label="__('passenger.field.meter_total')" :value="old('meter_total', $settlement?->meter_total)" required :hint="__('passenger.hint.meter_total')" />
        <x-input-field name="cash_total" type="number" step="0.01" min="0" :label="__('passenger.field.cash_total')" :value="old('cash_total', $settlement?->cash_total)" />
        <x-input-field name="card_total" type="number" step="0.01" min="0" :label="__('passenger.field.card_total')" :value="old('card_total', $settlement?->card_total)" />
        <x-input-field name="voucher_total" type="number" step="0.01" min="0" :label="__('passenger.field.voucher_total')" :value="old('voucher_total', $settlement?->voucher_total)" />
        <x-input-field name="invoice_total" type="number" step="0.01" min="0" :label="__('passenger.field.invoice_total')" :value="old('invoice_total', $settlement?->invoice_total)" />
        <x-input-field name="mediator_total" type="number" step="0.01" min="0" :label="__('passenger.field.mediator_total')" :value="old('mediator_total', $settlement?->mediator_total)" />
        <x-input-field name="tip_total" type="number" step="0.01" min="0" :label="__('passenger.field.tip_total')" :value="old('tip_total', $settlement?->tip_total)" :hint="__('passenger.hint.tip_total')" />
        <x-input-field name="cancelled_total" type="number" step="0.01" min="0" :label="__('passenger.field.cancelled_total')" :value="old('cancelled_total', $settlement?->cancelled_total)" />
    </x-form-group>

    <x-validation-errors />
</x-modal>

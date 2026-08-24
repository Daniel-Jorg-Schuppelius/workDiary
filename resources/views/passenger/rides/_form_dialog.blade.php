{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Fahrt annehmen (MVP-456) — Betriebsart wird bei Annahme eingefroren. --}}
<x-modal
    :title="__('passenger.rides.action.create')"
    :eyebrow="__('passenger.rides.title')"
    icon="local_taxi"
    tone="primary"
    :action="route('passenger-rides.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('passenger.rides.action.accept')"
>
    <x-form-group :legend="__('passenger.section.order')" icon="local_taxi" tone="primary" cols="2">
        <x-select-field name="operation_mode" :label="__('passenger.field.operation_mode')" required>
            @foreach (\App\Enums\Passenger\RideOperationMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('operation_mode', \App\Enums\Passenger\RideOperationMode::Taxi->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="order_channel" :label="__('passenger.field.order_channel')" required>
            @foreach (\App\Enums\Passenger\RideOrderChannel::cases() as $channel)
                <option value="{{ $channel->value }}" @selected(old('order_channel') === $channel->value)>{{ $channel->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('passenger.field.customer_optional')" span="2">
            <option value="">{{ __('passenger.field.without_customer') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected((string) old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="order_receipt_reference" :label="__('passenger.field.order_receipt_reference')" :value="old('order_receipt_reference')" span="2" :hint="__('passenger.hint.order_receipt')" />
    </x-form-group>

    <x-form-group :legend="__('passenger.section.route')" icon="route" tone="primary" cols="2">
        <x-input-field name="pickup_address" :label="__('passenger.field.pickup_address')" :value="old('pickup_address')" required span="2" />
        <x-input-field name="destination_address" :label="__('passenger.field.destination_address')" :value="old('destination_address')" span="2" />
        <div class="flex items-center gap-2">
            <input type="hidden" name="destination_open" value="0">
            <input type="checkbox" id="ride-destination-open" name="destination_open" value="1" class="checkbox checkbox-sm" @checked(old('destination_open'))>
            <label for="ride-destination-open" class="text-sm">{{ __('passenger.field.destination_open') }}</label>
        </div>
        <x-date-range layout="split" grid-class="contents" form-control size="" type="datetime-local"
                      from-name="window_start" to-name="window_end"
                      from-id="window_start" to-id="window_end"
                      :from-label="__('passenger.field.window_start')"
                      :to-label="__('passenger.field.window_end')"
                      :from="old('window_start')" :to="old('window_end')"
                      :from-error="$errors->first('window_start') ?: null"
                      :to-error="$errors->first('window_end') ?: null" />
    </x-form-group>

    <x-form-group :legend="__('passenger.section.passengers')" icon="groups" tone="primary" cols="3">
        <x-input-field name="passenger_count" type="number" :label="__('passenger.field.passenger_count')" :value="old('passenger_count', 1)" min="1" max="60" required />
        <x-input-field name="luggage_count" type="number" :label="__('passenger.field.luggage_count')" :value="old('luggage_count', 0)" min="0" max="60" />
        <x-input-field name="child_seats" type="number" :label="__('passenger.field.child_seats')" :value="old('child_seats', 0)" min="0" max="10" />
        <div class="flex items-center gap-2">
            <input type="hidden" name="wheelchair" value="0">
            <input type="checkbox" id="ride-wheelchair" name="wheelchair" value="1" class="checkbox checkbox-sm" @checked(old('wheelchair'))>
            <label for="ride-wheelchair" class="text-sm">{{ __('passenger.field.wheelchair') }}</label>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="barrier_free_required" value="0">
            <input type="checkbox" id="ride-barrier-free" name="barrier_free_required" value="1" class="checkbox checkbox-sm" @checked(old('barrier_free_required'))>
            <label for="ride-barrier-free" class="text-sm">{{ __('passenger.field.barrier_free_required') }}</label>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="animal" value="0">
            <input type="checkbox" id="ride-animal" name="animal" value="1" class="checkbox checkbox-sm" @checked(old('animal'))>
            <label for="ride-animal" class="text-sm">{{ __('passenger.field.animal') }}</label>
        </div>
        <x-input-field name="passenger_name" :label="__('passenger.field.passenger_name')" :value="old('passenger_name')" :hint="__('passenger.hint.data_minimal')" />
        <x-input-field name="passenger_contact" :label="__('passenger.field.passenger_contact')" :value="old('passenger_contact')" span="2" />
    </x-form-group>

    <x-validation-errors />
</x-modal>

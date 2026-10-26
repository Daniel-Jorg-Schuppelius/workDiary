{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wohnung anlegen/bearbeiten (Branchenprofil Vermieter) — erwartet: $profile, $rateCards --}}
@php($modes = \App\Enums\Rental\RentalMode::class)
<x-form-group :legend="__('housing.unit')" icon="apartment" cols="2">
    <x-input-field class="input-sm" name="name" :label="__('Name')" required maxlength="255" :value="old('name', $profile->asset?->name)" />
    <x-select-field class="select-sm" name="rental_mode" :label="__('housing.mode')" required>
        @foreach ($modes::cases() as $mode)
            <option value="{{ $mode->value }}" @selected(old('rental_mode', $profile->rental_mode?->value ?? $modes::Both->value) === $mode->value)>{{ $mode->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field class="input-sm" name="location_text" :label="__('housing.address')" maxlength="255" :value="old('location_text', $profile->asset?->location_text)" span="2" />
    <x-textarea-field class="textarea-sm" name="public_description" :label="__('housing.public_description')" maxlength="4000" rows="3" span="2">{{ old('public_description', $profile->public_description) }}</x-textarea-field>
    <x-checkbox-field name="is_rentable" :label="__('housing.rentable')" :checked="(bool) old('is_rentable', $profile->is_rentable ?? true)" />
    <x-checkbox-field name="portal_bookable" :label="__('housing.publish')" :checked="(bool) old('portal_bookable', $profile->portal_bookable ?? false)" />
</x-form-group>
<x-form-group :legend="$modes::LongTerm->label()" icon="key" cols="2" :description="__('housing.rent_hint')">
    <x-input-field class="input-sm" name="rent_amount" type="number" step="0.01" min="0" :label="__('housing.rent_amount')" :value="old('rent_amount', $profile->rent_amount?->getAmount())" />
    <x-input-field class="input-sm" name="ancillary_amount" type="number" step="0.01" min="0" :label="__('housing.ancillary_amount')" :value="old('ancillary_amount', $profile->ancillary_amount?->getAmount())" />
</x-form-group>
<x-form-group :legend="$modes::Holiday->label()" icon="holiday_village" cols="2" :description="__('housing.price_hint')">
    <x-input-field class="input-sm" name="max_guests" type="number" min="1" max="100" required :label="__('housing.max_guests')" :value="old('max_guests', $profile->max_guests ?? 2)" />
    <x-input-field class="input-sm" name="minimum_nights" type="number" min="1" max="365" required :label="__('housing.minimum_nights')" :value="old('minimum_nights', $profile->minimum_nights ?? 1)" />
    <x-input-field class="input-sm" name="check_in_time" type="time" required :label="__('housing.check_in')" :value="old('check_in_time', $profile->check_in_time ?? '15:00')" />
    <x-input-field class="input-sm" name="check_out_time" type="time" required :label="__('housing.check_out')" :value="old('check_out_time', $profile->check_out_time ?? '10:00')" />
    <x-select-field class="select-sm" name="default_rate_card_id" :label="__('Standard-Preisliste')">
        <option value="">{{ __('keine') }}</option>
        @foreach ($rateCards as $card)<option value="{{ $card->sqid }}" @selected((string) old('default_rate_card_id', $profile->defaultRateCard?->sqid) === $card->sqid)>{{ $card->name }}</option>@endforeach
    </x-select-field>
    <x-input-field class="input-sm" name="buffer_after_hours" type="number" min="0" max="720" required :label="__('housing.cleaning_buffer')" :value="old('buffer_after_hours', $profile->buffer_after_hours ?? 0)" />
</x-form-group>
<x-validation-errors />

{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tenancy_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mietverhältnis anlegen/bearbeiten — erwartet: $tenancy, $profiles, $customers. Status: der aktuelle (solange offen) und die erlaubten Folgezustände. --}}
@php
    $statuses = $tenancy->exists
        ? array_values(array_filter(
            [...($tenancy->status->isOpen() ? [$tenancy->status] : []), ...$tenancy->status->allowedTransitions()],
            static fn (\App\Enums\Rental\RentalTenancyStatus $status): bool => $status !== \App\Enums\Rental\RentalTenancyStatus::Requested,
        ))
        : [];
    $defaults = $tenancy->asset?->rentalProfile;
@endphp
<x-form-group :legend="__('housing.tenancy')" icon="key" cols="2" :description="__('housing.tenancy_hint')">
    @if ($tenancy->exists)
        <p class="md:col-span-2">{{ $tenancy->asset?->name }} · {{ $tenancy->customer?->name }}</p>
        <x-select-field class="select-sm" name="status" :label="__('Status')" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $tenancy->status->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select-field>
    @else
        <x-select-field class="select-sm" name="profile_id" :label="__('housing.unit')" required>
            @foreach ($profiles as $profile)<option value="{{ $profile->sqid }}" @selected(old('profile_id') === $profile->sqid)>{{ $profile->asset?->name }}</option>@endforeach
        </x-select-field>
        <x-select-field class="select-sm" name="customer_id" :label="__('housing.tenant')" required>
            @foreach ($customers as $customer)<option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>@endforeach
        </x-select-field>
    @endif
    <x-date-range from-name="starts_on" to-name="ends_on" from-id="tenancy-start" to-id="tenancy-end" :from="old('starts_on', $tenancy->starts_on?->format('Y-m-d'))" :to="old('ends_on', $tenancy->ends_on?->format('Y-m-d'))"
        :from-label="__('housing.starts_on')" :to-label="__('housing.ends_on')" :from-required="true" :to-required="false" layout="split" form-control class="md:col-span-2" />
    <x-input-field class="input-sm" name="rent_amount" type="number" step="0.01" min="0" :label="__('housing.rent_amount')" :value="old('rent_amount', ($tenancy->rent_amount ?? $defaults?->rent_amount)?->getAmount())" />
    <x-input-field class="input-sm" name="ancillary_amount" type="number" step="0.01" min="0" :label="__('housing.ancillary_amount')" :value="old('ancillary_amount', ($tenancy->ancillary_amount ?? $defaults?->ancillary_amount)?->getAmount())" />
    <x-input-field class="input-sm" name="deposit_amount" type="number" step="0.01" min="0" :label="__('housing.deposit')" :value="old('deposit_amount', $tenancy->deposit_amount?->getAmount())" />
    <x-textarea-field class="textarea-sm" name="note" :label="__('Notizen')" rows="2" maxlength="1000" span="2">{{ old('note', $tenancy->note) }}</x-textarea-field>
</x-form-group>
<x-validation-errors />

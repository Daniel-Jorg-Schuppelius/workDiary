{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _ownership_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset\Asset $asset */
    $currentCustomerSqid = \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $asset->customer_id);
@endphp

<x-modal
    :title="__('asset.ownership.title')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="swap_horiz"
    tone="primary"
    size="md"
    :action="route('assets.ownership', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('asset.ownership.action')">

    <p class="mb-2 text-sm text-base-content/70">
        {{ __('asset.ownership.current', ['owner' => $asset->owned_by->label() . ($asset->customer ? ' — ' . $asset->customer->name : '')]) }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="owned_by" :label="__('asset.ownership.owned_by')" required>
            @foreach (\App\Enums\Asset\AssetOwnership::cases() as $ownership)
                <option value="{{ $ownership->value }}" @selected(old('owned_by', $asset->owned_by->value) === $ownership->value)>{{ $ownership->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('Kunde')" :hint="__('asset.ownership.customer_hint')">
            <option value="">{{ __('— keiner —') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected(old('customer_id', $currentCustomerSqid) === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-textarea-field name="note" :label="__('asset.ownership.note')" rows="2" maxlength="1000" :value="old('note')" />
</x-modal>

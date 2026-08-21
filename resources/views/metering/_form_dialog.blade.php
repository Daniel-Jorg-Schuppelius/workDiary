{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zählerstands-Vereinbarung (Feature 116, MVP-605).
--}}
<x-modal
    :title="$agreement === null ? __('metering.action.create') : __('metering.action.edit')"
    :eyebrow="__('metering.title')"
    icon="speed"
    :action="$agreement === null ? route('metering.store') : route('metering.update', $agreement)"
    :method="$agreement === null ? 'POST' : 'PUT'"
    :submit-label="__('Speichern')"
>
    <x-input-field name="title" type="text" required maxlength="191"
                   :label="__('metering.column.title')"
                   :value="old('title', $agreement?->title)" />

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="m-form-customer"><span class="label-text">{{ __('metering.column.customer') }}</span></label>
            <select id="m-form-customer" name="customer_id" class="select select-bordered w-full">
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}" @selected(old('customer_id', $agreement?->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer::class, (int) $agreement->customer_id) : null) === $c->sqid)>{{ $c->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="m-form-asset"><span class="label-text">{{ __('metering.column.asset') }}</span></label>
            <select id="m-form-asset" name="asset_id" class="select select-bordered w-full">
                @foreach ($assets as $a)
                    <option value="{{ $a->sqid }}" @selected(old('asset_id', $agreement?->asset_id !== null ? \App\Support\Sqid::encode(\App\Models\Asset::class, (int) $agreement->asset_id) : null) === $a->sqid)>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="base_price" type="number" step="0.01" min="0"
                       :label="__('metering.column.base_price')"
                       :value="old('base_price', $agreement?->base_price ?? '0.00')" />
        <x-input-field name="unit_price" type="number" step="0.0001" min="0" required
                       :label="__('metering.column.unit_price')"
                       :value="old('unit_price', $agreement?->unit_price)" />
        <x-input-field name="free_units" type="number" step="0.001" min="0"
                       :label="__('metering.column.free_units')"
                       :value="old('free_units', $agreement?->free_units ?? '0')" />
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="unit" type="text" maxlength="32"
                       :label="__('metering.column.unit')"
                       :value="old('unit', $agreement?->unit)" />
        <div>
            <label class="label" for="m-form-interval"><span class="label-text">{{ __('metering.column.interval') }}</span></label>
            <select id="m-form-interval" name="interval_unit" class="select select-bordered w-full">
                @foreach (['monthly', 'quarterly', 'yearly'] as $unit)
                    <option value="{{ $unit }}" @selected(old('interval_unit', $agreement?->interval_unit ?? 'monthly') === $unit)>{{ __('metering.interval.' . $unit) }}</option>
                @endforeach
            </select>
        </div>
        <x-input-field name="interval_count" type="number" min="1" max="12"
                       :label="__('metering.column.interval_count')"
                       :value="old('interval_count', $agreement?->interval_count ?? 1)" />
    </div>

    <x-date-range layout="split" from-name="next_run_on" to-name="end_on" from-required
                  :from-label="__('metering.column.next_run_on')"
                  :to-label="__('metering.column.end_on')"
                  :from="old('next_run_on', $agreement?->next_run_on?->toDateString() ?? now()->endOfMonth()->toDateString())"
                  :to="old('end_on', $agreement?->end_on?->toDateString())" />

    <p class="text-xs text-base-content/60">{{ __('metering.form_hint') }}</p>
</x-modal>

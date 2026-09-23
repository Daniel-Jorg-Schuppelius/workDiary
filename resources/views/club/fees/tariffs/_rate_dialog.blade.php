{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tarifsatz anlegen/bearbeiten (in #entry-modal geladen). Variablen: $tariff, $rate|null, $intervals, $prorations --}}
<x-modal
    :title="$rate ? __('club.action.edit') : __('club.fees.action.create_rate')"
    :eyebrow="$tariff->name"
    icon="payments"
    tone="primary"
    :action="$rate ? route('club.fees.rates.update', $rate) : route('club.fees.rates.store', $tariff)"
    :method="$rate ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.field.rates')" icon="payments" tone="primary" cols="3" :description="__('club.fees.hint.rate')">
        <x-input-field name="valid_from" type="date" :label="__('club.field.valid_from')" required :value="old('valid_from', $rate?->valid_from?->toDateString())" />
        <x-input-field name="amount" inputmode="decimal" :label="__('club.fees.field.amount')" required maxlength="20" :value="old('amount', $rate?->amount?->getAmount())" :hint="__('club.fees.hint.amount')" />
        <x-select-field name="interval" :label="__('club.fees.field.interval')" required>
            @foreach ($intervals as $interval)
                <option value="{{ $interval->value }}" @selected(old('interval', $rate?->interval->value ?? 'monthly') === $interval->value)>{{ $interval->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="anchor_month" type="number" min="1" max="12" :label="__('club.fees.field.anchor_month')" required :value="old('anchor_month', $rate?->anchor_month ?? 1)" :hint="__('club.fees.hint.anchor_month')" />
        <x-input-field name="due_days" type="number" min="0" max="365" :label="__('club.fees.field.due_days')" required :value="old('due_days', $rate?->due_days ?? 14)" />
        <x-select-field name="proration" :label="__('club.fees.field.proration')" required :hint="__('club.fees.hint.proration')">
            @foreach ($prorations as $proration)
                <option value="{{ $proration->value }}" @selected(old('proration', $rate?->proration->value ?? 'full') === $proration->value)>{{ $proration->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="admission_fee" inputmode="decimal" :label="__('club.fees.field.admission_fee')" maxlength="20" :value="old('admission_fee', $rate?->admission_fee?->getAmount())" :hint="__('club.fees.hint.admission_fee')" />
    </x-form-group>
</x-modal>

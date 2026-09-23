{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _surcharge_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abteilungszuschlag anlegen/bearbeiten (in #entry-modal geladen). Variablen: $surcharge|null, $intervals, $departments --}}
@php $selectedDepartment = old('club_department_id', $surcharge?->club_department_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubDepartment::class, $surcharge->club_department_id) : ''); @endphp
<x-modal
    :title="$surcharge ? __('club.action.edit') : __('club.fees.action.create_surcharge')"
    :eyebrow="__('club.fees.card.surcharges')"
    icon="add_circle"
    tone="primary"
    :action="$surcharge ? route('club.fees.surcharges.update', $surcharge) : route('club.fees.surcharges.store')"
    :method="$surcharge ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.surcharges')" icon="add_circle" tone="primary" cols="2" :description="__('club.fees.hint.surcharge')">
        <x-input-field name="name" :label="__('club.fees.field.name')" required maxlength="120" :value="old('name', $surcharge?->name)" />
        <x-select-field name="club_department_id" :label="__('club.field.department')" required>
            <option value="">–</option>
            @foreach ($departments as $department)
                <option value="{{ $department->sqid }}" @selected((string) $selectedDepartment === $department->sqid)>{{ $department->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="amount" inputmode="decimal" :label="__('club.fees.field.amount')" required maxlength="20" :value="old('amount', $surcharge?->amount?->getAmount())" />
        <x-select-field name="interval" :label="__('club.fees.field.interval')" required>
            @foreach ($intervals as $interval)
                <option value="{{ $interval->value }}" @selected(old('interval', $surcharge?->interval->value ?? 'monthly') === $interval->value)>{{ $interval->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="anchor_month" type="number" min="1" max="12" :label="__('club.fees.field.anchor_month')" required :value="old('anchor_month', $surcharge?->anchor_month ?? 1)" />
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="valid_from" to-name="valid_to" type="date"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('valid_from', $surcharge?->valid_from?->toDateString())"
                      :to="old('valid_to', $surcharge?->valid_to?->toDateString())" />
    </x-form-group>
</x-modal>

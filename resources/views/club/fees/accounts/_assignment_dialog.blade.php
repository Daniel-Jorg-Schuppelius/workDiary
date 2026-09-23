{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assignment_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zuordnung anlegen/bearbeiten (in #entry-modal geladen). Variablen: $account, $assignment|null, $members, $tariffs --}}
@php
    $selectedMember = old('club_member_id', $assignment?->member?->sqid ?? '');
    $selectedTariff = old('club_fee_tariff_id', $assignment?->tariff?->sqid ?? '');
@endphp
<x-modal
    :title="$assignment ? __('club.action.edit') : __('club.fees.action.assign')"
    :eyebrow="$account->name"
    icon="person_add"
    tone="primary"
    :action="$assignment ? route('club.fees.assignments.update', [$account, $assignment]) : route('club.fees.assignments.store', $account)"
    :method="$assignment ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.assignments')" icon="person_add" tone="primary" cols="2" :description="__('club.fees.hint.assignment')">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required>
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected((string) $selectedMember === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="club_fee_tariff_id" :label="__('club.fees.field.tariff')" required>
            <option value="">–</option>
            @foreach ($tariffs as $tariff)
                <option value="{{ $tariff->sqid }}" @selected((string) $selectedTariff === $tariff->sqid)>{{ $tariff->name }} ({{ $tariff->kind->label() }})</option>
            @endforeach
        </x-select-field>
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="valid_from" to-name="valid_to" type="date"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('valid_from', $assignment?->valid_from?->toDateString())"
                      :to="old('valid_to', $assignment?->valid_to?->toDateString())" />
        <x-input-field name="discount_percent" type="number" step="0.01" min="0" max="100" :label="__('club.fees.field.discount')" :value="old('discount_percent', $assignment?->discount_percent)" :hint="__('club.fees.hint.discount')" />
        <x-input-field name="discount_reason" :label="__('club.fees.field.discount_reason')" maxlength="255" :value="old('discount_reason', $assignment?->discount_reason)" />
    </x-form-group>
</x-modal>

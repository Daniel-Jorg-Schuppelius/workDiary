{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _exemption_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Befreiung/Ermäßigung (in #entry-modal geladen). Variablen: $account, $member, $exemption|null, $kinds --}}
<x-modal
    :title="$exemption ? __('club.action.edit') : __('club.fees.action.add_exemption')"
    :eyebrow="$member->fullName()"
    icon="money_off"
    tone="warning"
    :action="$exemption ? route('club.fees.exemptions.update', [$account, $exemption]) : route('club.fees.exemptions.store', [$account, $member])"
    :method="$exemption ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.exemptions')" icon="money_off" tone="warning" cols="2" :description="__('club.fees.hint.exemption')">
        <x-select-field name="kind" :label="__('club.fees.field.exemption_kind')" required>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $exemption?->kind->value ?? 'exemption') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="percent" type="number" step="0.01" min="0" max="100" :label="__('club.fees.field.percent')" :value="old('percent', $exemption?->percent)" :hint="__('club.fees.hint.percent')" />
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      from-name="starts_on" to-name="ends_on" type="date"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('starts_on', $exemption?->starts_on?->toDateString())"
                      :to="old('ends_on', $exemption?->ends_on?->toDateString())" />
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" span="2" :value="old('reason', $exemption?->reason)" />
    </x-form-group>
</x-modal>

{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _kind_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Art-Wechsel / Pause zum Stichtag (in #entry-modal geladen).
  Variablen: $member (ClubMember), $today (CarbonImmutable)
--}}
<x-modal
    :title="__('club.action.change_kind')"
    :eyebrow="$member->displayNo() . ' · ' . $member->fullName()"
    icon="how_to_reg"
    tone="primary"
    :action="route('club.members.kind.update', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.field.kind')" icon="how_to_reg" tone="primary" cols="2" :description="__('club.hint.kind_change')">
        <x-select-field name="kind" :label="__('club.field.kind')" required>
            @foreach (\App\Enums\Club\ClubMembershipKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $member->kind->value) === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="effective_on" type="date" :label="__('club.field.effective_on')" required :value="old('effective_on', $today->toDateString())" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="2" :value="old('note')" />
    </x-form-group>
</x-modal>

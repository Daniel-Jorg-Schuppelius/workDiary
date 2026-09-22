{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _leave_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Austritt erfassen (in #entry-modal geladen).
  Variablen: $member (ClubMember), $today (CarbonImmutable)
--}}
<x-modal
    :title="__('club.action.leave')"
    :eyebrow="$member->displayNo() . ' · ' . $member->fullName()"
    icon="logout"
    tone="warning"
    :action="route('club.members.leave', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.action.leave')">

    <x-form-group :legend="__('club.field.left_on')" icon="logout" tone="warning" cols="2" :description="__('club.hint.leave')">
        <x-input-field name="left_on" type="date" :label="__('club.field.left_on')" required :value="old('left_on', $today->toDateString())" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>

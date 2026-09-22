{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _end_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Gruppenzuordnung beenden (in #entry-modal geladen).
  Variablen: $group, $membership (mit member), $today
--}}
<x-modal
    :title="__('club.action.end')"
    :eyebrow="$group->name . ' · ' . $membership->member?->fullName()"
    icon="person_remove"
    tone="warning"
    :action="route('club.groups.memberships.end', [$group, $membership])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.action.end')">

    <x-form-group :legend="__('club.field.valid_to')" icon="event_busy" tone="warning" cols="2">
        <x-input-field name="valid_to" type="date" :label="__('club.field.valid_to')" required :value="old('valid_to', $today->toDateString())" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>

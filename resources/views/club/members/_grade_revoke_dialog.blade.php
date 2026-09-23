{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _grade_revoke_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Grad widerrufen (in #entry-modal geladen). Variablen: $member, $memberGrade --}}
<x-modal
    :title="__('club.grading.action.revoke')"
    :eyebrow="$member->fullName() . ' · ' . ($memberGrade->grade?->name ?? '')"
    icon="undo"
    tone="warning"
    :action="route('club.members.grades.revoke', [$member, $memberGrade])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.grading.action.revoke')">

    <x-form-group :legend="__('club.attendance.field.reason')" icon="undo" tone="warning" cols="1" :description="__('club.grading.hint.revoke')">
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>

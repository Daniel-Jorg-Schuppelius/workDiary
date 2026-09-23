{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _grade_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorhandenen Grad anerkennen (in #entry-modal geladen). Variablen: $member, $systems (mit grades), $today --}}
<x-modal
    :title="__('club.grading.action.recognize')"
    :eyebrow="$member->fullName()"
    icon="workspace_premium"
    tone="primary"
    :action="route('club.members.grades.store', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.field.grade')" icon="workspace_premium" tone="primary" cols="2" :description="__('club.grading.hint.recognize')">
        <x-select-field name="club_grade_id" :label="__('club.grading.field.grade')" required span="2">
            <option value="">–</option>
            @foreach ($systems as $system)
                <optgroup label="{{ $system->name }} · {{ $system->discipline }}">
                    @foreach ($system->grades as $grade)
                        <option value="{{ $grade->sqid }}" @selected((string) old('club_grade_id') === $grade->sqid)>{{ $grade->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>
        <x-input-field name="obtained_on" type="date" :label="__('club.grading.field.obtained_on')" required :value="old('obtained_on', $today->toDateString())" />
        <x-input-field name="evidence" :label="__('club.grading.field.evidence')" maxlength="255" :value="old('evidence')" :hint="__('club.grading.hint.evidence')" />
    </x-form-group>
</x-modal>

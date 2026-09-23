{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _candidate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kandidat anlegen (in #entry-modal geladen). Variablen: $offer (mit targetGrades), $targets, $others --}}
<x-modal
    :title="__('club.exams.action.add_candidate')"
    :eyebrow="$offer->event?->title"
    icon="person_add"
    tone="primary"
    :action="route('club.exams.candidates.store', $offer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.field.member')" icon="person_add" tone="primary" cols="2" :description="__('club.exams.hint.add_candidate')">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required>
            <option value="">–</option>
            @if ($targets->isNotEmpty())
                <optgroup label="{{ __('club.events.field.target_list') }}">
                    @foreach ($targets as $member)
                        <option value="{{ $member->sqid }}" @selected((string) old('club_member_id') === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if ($others->isNotEmpty())
                <optgroup label="{{ __('club.events.label.other_members') }}">
                    @foreach ($others as $member)
                        <option value="{{ $member->sqid }}" @selected((string) old('club_member_id') === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
                    @endforeach
                </optgroup>
            @endif
        </x-select-field>
        <x-select-field name="target_grade_id" :label="__('club.exams.field.target_grade')" required>
            @foreach ($offer->targetGrades as $grade)
                <option value="{{ $grade->sqid }}" @selected((string) old('target_grade_id') === $grade->sqid)>{{ $grade->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>

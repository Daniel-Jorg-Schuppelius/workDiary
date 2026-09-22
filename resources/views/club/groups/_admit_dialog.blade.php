{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _admit_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Mitglied aufnehmen bzw. Antrag vormerken (in #entry-modal geladen).
  Variablen: $group, $candidates (Collection<ClubMember>), $today, $canOverride
--}}
<x-modal
    :title="__('club.action.admit')"
    :eyebrow="$group->name"
    icon="person_add"
    tone="primary"
    :action="route('club.groups.admit', $group)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    @if ($candidates->isEmpty())
        <x-empty-state icon="person_add" :title="__('club.empty.candidates')" compact />
    @else
        <x-form-group :legend="__('club.field.member')" icon="person_add" tone="primary" cols="2">
            <x-select-field name="club_member_id" :label="__('club.field.member')" required span="2">
                <option value="">–</option>
                @foreach ($candidates as $candidate)
                    @php($candidateAge = $candidate->ageOn($today))
                    <option value="{{ $candidate->sqid }}" @selected((string) old('club_member_id') === $candidate->sqid)>
                        {{ $candidate->fullName() }} · {{ $candidate->displayNo() }}@if ($candidateAge !== null) · {{ trans_choice('club.label.age_years', $candidateAge, ['age' => $candidateAge]) }}@else · {{ __('club.label.without_birth_date') }}@endif
                    </option>
                @endforeach
            </x-select-field>
            <x-input-field name="valid_from" type="date" :label="__('club.field.valid_from')" required :value="old('valid_from', $today->toDateString())" />
            <x-select-field name="mode" :label="__('club.field.mode')" required :hint="__('club.hint.request')">
                <option value="admit" @selected(old('mode', 'admit') === 'admit')>{{ __('club.label.mode_admit') }}</option>
                <option value="request" @selected(old('mode') === 'request')>{{ __('club.label.mode_request') }}</option>
            </x-select-field>
            <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="2" :value="old('note')" />
            @if ($canOverride)
                <x-checkbox-field name="override" :label="__('club.field.override')" :checked="(bool) old('override', false)" span="2" :hint="__('club.hint.override')" />
            @endif
        </x-form-group>
    @endif
</x-modal>

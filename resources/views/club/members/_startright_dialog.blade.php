{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _startright_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Startrecht dokumentieren (in #entry-modal geladen). Variablen: $member, $profiles. --}}
<x-modal
    :title="__('club.competitions.action.grant_start_right')"
    :eyebrow="$member->fullName()"
    icon="badge"
    tone="primary"
    :action="route('club.members.startrights.store', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.competitions.card.start_rights')" icon="badge" tone="primary" cols="2" :description="__('club.competitions.hint.start_right')">
        <x-select-field name="club_sport_profile_id" :label="__('club.teams.field.profile')" :hint="__('club.competitions.hint.start_right_profile')">
            <option value="">{{ __('club.competitions.label.all_profiles') }}</option>
            @foreach ($profiles as $profile)
                <option value="{{ $profile->sqid }}" @selected(old('club_sport_profile_id') === $profile->sqid)>{{ $profile->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="reference" :label="__('club.competitions.field.reference')" maxlength="120" :value="old('reference')" />
        <x-date-range class="md:col-span-2" layout="split" form-control from-name="valid_from" to-name="valid_to"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('valid_from', now()->toDateString())" :to="old('valid_to')" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" span="2" :value="old('note')" />
    </x-form-group>
</x-modal>

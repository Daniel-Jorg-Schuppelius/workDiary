{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _squad_member_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Kaderzuordnung anlegen/bearbeiten (in #entry-modal geladen). Variablen: $group, $squad (ClubSquad),
  $entry (ClubSquadMember|null), $profile (ClubSportProfile|null), $members (Auswahl bei Anlage).
  Gastspieler: Herkunftsverein, ohne Mitgliedschaft, Beitrag oder Login im eigenen Verein.
--}}
@php
    $isEdit = $entry !== null;
    $positions = $profile?->positions ?? [];
    $isRacket = $profile?->hasPairings() ?? false;
@endphp
<x-modal
    :title="$isEdit ? ($entry->member?->fullName() ?? __('club.action.edit')) : __('club.teams.action.add_squad_member')"
    :eyebrow="$group->name . ' · ' . ($squad->season?->name ?? '')"
    icon="group_add"
    tone="primary"
    :action="$isEdit ? route('club.groups.squad.update', [$group, $entry]) : route('club.groups.squad.store', $group)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <input type="hidden" name="season" value="{{ $squad->season?->sqid }}">

    @unless ($isEdit)
        <x-form-group :legend="__('club.field.member')" icon="person" tone="primary" cols="2" :description="__('club.teams.hint.squad_member')">
            <x-select-field name="club_member_id" :label="__('club.teams.field.existing_member')" span="2">
                <option value="">{{ __('club.teams.label.new_guest') }}</option>
                @foreach ($members as $member)
                    <option value="{{ $member->sqid }}" @selected(old('club_member_id') === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})@if ($member->kind === \App\Enums\Club\ClubMembershipKind::Guest) · {{ $member->kind->label() }}@endif</option>
                @endforeach
            </x-select-field>
            <x-input-field name="guest_first_name" :label="__('club.field.first_name')" maxlength="120" :value="old('guest_first_name')" />
            <x-input-field name="guest_last_name" :label="__('club.field.last_name')" maxlength="120" :value="old('guest_last_name')" />
            <x-input-field name="guest_birth_date" type="date" :label="__('club.field.birth_date')" :value="old('guest_birth_date')" />
            <x-input-field name="guest_origin" :label="__('club.teams.field.guest_origin')" maxlength="120" :value="old('guest_origin')" :hint="__('club.teams.hint.guest_origin')" />
        </x-form-group>
    @endunless

    <x-form-group :legend="__('club.teams.field.squad')" icon="group_add" tone="primary" cols="2">
        @if ($isEdit)
            <x-input-field name="guest_origin" :label="__('club.teams.field.guest_origin')" maxlength="120" span="2" :value="old('guest_origin', $entry->guest_origin)" :hint="__('club.teams.hint.guest_origin')" />
        @endif
        @if ($isEdit)
            <x-input-field name="valid_to" type="date" :label="__('club.field.valid_to')" :value="old('valid_to', $entry->valid_to?->toDateString())" :hint="__('club.field.valid_from') . ': ' . $entry->valid_from->format('d.m.Y')" />
        @else
            <x-date-range class="md:col-span-2" layout="split" form-control from-name="valid_from" to-name="valid_to"
                          :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                          :from="old('valid_from', $squad->season?->starts_on?->toDateString())" :to="old('valid_to')" />
        @endif
        <x-input-field name="jersey_no" type="number" min="0" max="999" :label="__('club.teams.field.jersey_no')" :value="old('jersey_no', $entry?->jersey_no)" />
        @if ($positions !== [])
            <x-select-field name="position_code" :label="__('club.teams.field.position')">
                <option value="">–</option>
                @foreach ($positions as $position)
                    <option value="{{ $position['code'] }}" @selected(old('position_code', $entry?->position_code) === $position['code'])>{{ $position['label'] }}</option>
                @endforeach
            </x-select-field>
        @else
            <x-input-field name="position_code" :label="__('club.teams.field.position')" maxlength="30" :value="old('position_code', $entry?->position_code)" />
        @endif
        @if ($isRacket)
            <x-input-field name="strength_rank" type="number" min="1" max="999" :label="__('club.teams.field.strength_rank')" :value="old('strength_rank', $entry?->strength_rank)" :hint="__('club.teams.hint.strength_rank')" />
        @endif
        <x-input-field name="notes" :label="__('club.field.notes')" maxlength="255" span="2" :value="old('notes', $entry?->notes)" />
    </x-form-group>
</x-modal>

{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Spieltag anlegen/bearbeiten (in #entry-modal geladen). Variablen: $event (Event|null), $match (ClubMatchDetails|null),
  $preselectedTeam, $teams, $groups, $seasons, $leaders, $rooms, $formTz.
--}}
@php
    $isEdit = $event !== null;
    $inFormTz = static fn ($value): ?string => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->setTimezone($formTz)->format('Y-m-d\TH:i');
    $selectedTeam = old('club_group_id', $match?->team ? \App\Support\Sqid::encode(\App\Models\Club\ClubGroup::class, $match->club_group_id) : ($preselectedTeam?->sqid ?? ''));
    $selectedSeason = old('club_season_id', $match?->club_season_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubSeason::class, $match->club_season_id) : '');
    $selectedGroups = collect(old('club_group_ids', $event?->clubGroups->where('id', '!=', $match?->club_group_id)->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedLeader = old('leader_user_id', $event?->responsibleUser?->sqid ?? '');
    $selectedRoom = old('room_id', $event?->rooms->first()?->sqid ?? '');
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.matches.action.create')"
    :eyebrow="__('club.matches.title.index')"
    icon="sports_soccer"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.matches.update', $event) : route('club.matches.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.matches.action.create')">

    <x-form-group :legend="__('club.matches.card.match')" icon="sports_soccer" tone="primary" cols="2" :description="__('club.matches.hint.form')">
        <x-select-field name="club_group_id" :label="__('club.teams.field.team')" required :disabled="$isEdit">
            @unless ($isEdit)<option value="">–</option>@endunless
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected((string) $selectedTeam === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="club_season_id" :label="__('club.teams.field.season')" :hint="__('club.matches.hint.season_auto')">
            <option value="">{{ __('club.matches.label.season_auto') }}</option>
            @foreach ($seasons as $season)
                <option value="{{ $season->sqid }}" @selected((string) $selectedSeason === $season->sqid)>{{ $season->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="opponent_name" :label="__('club.matches.field.opponent')" required maxlength="150" :value="old('opponent_name', $match?->opponent_name)" />
        <x-input-field name="competition" :label="__('club.matches.field.competition')" maxlength="120" :value="old('competition', $match?->competition)" />
        <x-checkbox-field name="is_home" :label="__('club.matches.field.is_home')" :checked="(bool) old('is_home', $match?->is_home ?? true)" />
        <x-input-field name="venue" :label="__('club.matches.field.venue')" maxlength="200" :value="old('venue', $match?->venue)" :hint="__('club.matches.hint.venue')" />
        <x-input-field name="title" :label="__('club.events.field.title')" maxlength="200" span="2" :value="old('title', $event?->title)" :hint="__('club.matches.hint.title')" />
    </x-form-group>

    <x-form-group :legend="__('club.events.field.starts')" icon="schedule" tone="primary" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      type="datetime-local" from-name="started_at" to-name="ended_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('started_at', $inFormTz($event?->started_at))"
                      :to="old('ended_at', $inFormTz($event?->ended_at))" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
        <x-input-field name="meet_at" type="datetime-local" :label="__('club.matches.field.meet_at')" :value="old('meet_at', $inFormTz($match?->meet_at))" />
        <x-select-field name="room_id" :label="__('club.events.field.room')">
            <option value="">–</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->sqid }}" @selected((string) $selectedRoom === $room->sqid)>{{ $room->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="leader_user_id" :label="__('club.events.field.leader')">
            <option value="">–</option>
            @foreach ($leaders as $leader)
                <option value="{{ $leader->sqid }}" @selected((string) $selectedLeader === $leader->sqid)>{{ $leader->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="5000" span="2" :value="old('description', $event?->description)" />
    </x-form-group>

    <x-form-group :legend="__('club.matches.field.extra_groups')" icon="diversity_3" tone="ghost" cols="3" :description="__('club.matches.hint.extra_groups')">
        @foreach ($groups as $group)
            <x-checkbox-field name="club_group_ids[]" :id="'mt-group-' . $group->sqid" :value="$group->sqid" :label="$group->name" :checked="in_array($group->sqid, $selectedGroups, true)" :toggle="false" />
        @endforeach
    </x-form-group>
</x-modal>

{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Wettkampf anlegen/bearbeiten (in #entry-modal geladen). Variablen: $event|null, $competition|null,
  $profiles (mit disciplines), $groups, $leaders, $rooms, $formTz.
--}}
@php
    $isEdit = $event !== null;
    $inFormTz = static fn ($value): ?string => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->setTimezone($formTz)->format('Y-m-d\TH:i');
    $selectedProfile = old('club_sport_profile_id', $competition?->club_sport_profile_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubSportProfile::class, $competition->club_sport_profile_id) : '');
    $selectedDisciplines = collect(old('disciplines', $competition?->disciplines ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedGroups = collect(old('club_group_ids', $event?->clubGroups->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedLeader = old('leader_user_id', $event?->responsibleUser?->sqid ?? '');
    $selectedRoom = old('room_id', $event?->rooms->first()?->sqid ?? '');
    $editProfile = $competition?->profile;
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.competitions.action.create')"
    :eyebrow="__('club.competitions.title.index')"
    icon="emoji_events"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.competitions.update', $event) : route('club.competitions.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.competitions.action.create')">

    <x-form-group :legend="__('club.competitions.card.competition')" icon="emoji_events" tone="primary" cols="2" :description="__('club.competitions.hint.form')">
        <x-input-field name="title" :label="__('club.events.field.title')" required maxlength="200" span="2" :value="old('title', $event?->title)" />
        <x-select-field name="club_sport_profile_id" :label="__('club.teams.field.profile')" required :disabled="$isEdit" :hint="__('club.competitions.hint.profile')">
            @unless ($isEdit)<option value="">–</option>@endunless
            @foreach ($profiles as $profile)
                <option value="{{ $profile->sqid }}" @selected((string) $selectedProfile === $profile->sqid)>{{ $profile->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="entry_fee" :label="__('club.competitions.field.entry_fee')" maxlength="20" :value="old('entry_fee', $competition?->entry_fee?->getAmount())" :hint="__('club.competitions.hint.entry_fee')" />
        <x-input-field name="venue" :label="__('club.competitions.field.venue')" maxlength="200" :value="old('venue', $competition?->venue)" />
        <x-input-field name="organizer" :label="__('club.competitions.field.organizer')" maxlength="150" :value="old('organizer', $competition?->organizer)" />
        <x-checkbox-field name="requires_start_right" :label="__('club.competitions.field.requires_start_right')" :checked="(bool) old('requires_start_right', $competition?->requires_start_right ?? true)" />
        <x-select-field name="visibility" :label="__('club.events.field.visibility')">
            @foreach (\App\Enums\Club\ClubEventVisibility::cases() as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $event?->clubDetails?->visibility->value ?? 'club') === $visibility->value)>{{ $visibility->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $competition?->notes)" />
    </x-form-group>

    <x-form-group :legend="__('club.competitions.field.disciplines')" icon="checklist" tone="primary" cols="3" :description="__('club.competitions.hint.disciplines')">
        @foreach (($isEdit ? [$editProfile] : $profiles) as $profile)
            @continue($profile === null)
            @foreach ($profile->disciplines ?? [] as $discipline)
                <x-checkbox-field name="disciplines[]" :id="'disc-' . $profile->sqid . '-' . $discipline['code']" :value="$discipline['code']" :label="$discipline['label'] . (isset($discipline['unit']) && $discipline['unit'] ? ' (' . $discipline['unit'] . ')' : '') . ($isEdit ? '' : ' · ' . $profile->name)" :checked="in_array($discipline['code'], $selectedDisciplines, true)" :toggle="false" />
            @endforeach
        @endforeach
    </x-form-group>

    <x-form-group :legend="__('club.events.field.starts')" icon="schedule" tone="primary" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control required type="datetime-local" from-name="started_at" to-name="ended_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('started_at', $inFormTz($event?->started_at))" :to="old('ended_at', $inFormTz($event?->ended_at))" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
        <x-input-field name="registration_lead_hours" type="number" min="0" max="8760" :label="__('club.competitions.field.entry_deadline_hours')" :value="old('registration_lead_hours', $event?->clubDetails?->registration_lead_hours)" :hint="__('club.competitions.hint.entry_deadline')" />
        <x-input-field name="max_participants" type="number" min="1" max="9999" :label="__('club.events.field.max_participants')" :value="old('max_participants', $event?->max_participants)" />
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
    </x-form-group>

    <x-form-group :legend="__('club.events.field.groups')" icon="diversity_3" tone="ghost" cols="3" :description="__('club.competitions.hint.groups')">
        @foreach ($groups as $group)
            <x-checkbox-field name="club_group_ids[]" :id="'cp-group-' . $group->sqid" :value="$group->sqid" :label="$group->name" :checked="in_array($group->sqid, $selectedGroups, true)" :toggle="false" />
        @endforeach
    </x-form-group>
</x-modal>

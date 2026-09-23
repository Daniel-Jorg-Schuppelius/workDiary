{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Vereinstermin anlegen/bearbeiten (in #entry-modal geladen).
  Variablen: $event (Event|null), $details (ClubEventDetails|null), $departments, $groups,
             $leaders, $rooms, $formTz
--}}
@php
    $isEdit = $event !== null;
    $details ??= null;
    $inFormTz = static fn ($value): ?string => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->setTimezone($formTz)->format('Y-m-d\TH:i');
    $selectedGroups = collect(old('club_group_ids', $event?->clubGroups->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedDepartment = old('club_department_id', $details?->department?->sqid ?? '');
    $selectedLeader = old('leader_user_id', $event?->responsibleUser?->sqid ?? '');
    $selectedRoom = old('room_id', $event?->rooms->first()?->sqid ?? '');
    $inSeries = $isEdit && ($event->series_id !== null || $event->recurrence_rule !== null);
@endphp

<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.events.action.create')"
    :eyebrow="__('club.events.title.index')"
    icon="event"
    tone="primary"
    :action="$isEdit ? route('club.events.update', $event) : route('club.events.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.events.action.create')">

    <x-form-group :legend="__('club.card.master_data')" icon="event" tone="primary" cols="2">
        <x-input-field name="title" :label="__('club.events.field.title')" required maxlength="200" span="2" :value="old('title', $event?->title)" />
        <x-select-field name="kind" :label="__('club.events.field.kind')" required>
            @foreach (\App\Enums\Club\ClubEventKind::cases() as $kind)
                @continue(in_array($kind, [\App\Enums\Club\ClubEventKind::Match, \App\Enums\Club\ClubEventKind::Competition], true) && $details?->kind !== $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $details?->kind->value ?? 'training') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="visibility" :label="__('club.events.field.visibility')" required :hint="__('club.events.hint.visibility')">
            @foreach (\App\Enums\Club\ClubEventVisibility::cases() as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $details?->visibility->value ?? 'groups') === $visibility->value)>{{ $visibility->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="5000" span="2" :value="old('description', $event?->description)" />
    </x-form-group>

    <x-form-group :legend="__('club.events.field.groups')" icon="diversity_3" tone="primary" cols="2">
        @forelse ($groups as $group)
            <x-checkbox-field name="club_group_ids[]" :id="'ev-group-' . $group->sqid" :value="$group->sqid" :label="$group->name"
                              :checked="in_array($group->sqid, $selectedGroups, true)" :with-hidden="false" />
        @empty
            <p class="text-sm text-muted md:col-span-2">{{ __('club.empty.groups') }}</p>
        @endforelse
        <x-input-field name="discipline" :label="__('club.field.discipline')" maxlength="60" :value="old('discipline', $details?->discipline)" :hint="__('club.grading.hint.event_discipline')" />
        <x-select-field name="club_department_id" :label="__('club.field.department')" span="2">
            <option value="">{{ __('club.label.no_department') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->sqid }}" @selected((string) $selectedDepartment === $department->sqid)>{{ $department->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('club.events.field.period')" icon="schedule" tone="info" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      type="datetime-local" from-name="started_at" to-name="ended_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('started_at', $inFormTz($event?->started_at))"
                      :to="old('ended_at', $inFormTz($event?->ended_at))" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
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
        @unless ($isEdit)
            <x-select-field name="recurrence" :label="__('club.events.field.recurrence')" :hint="__('club.events.hint.series')">
                @foreach (['none', 'weekly', 'biweekly', 'monthly'] as $option)
                    <option value="{{ $option }}" @selected(old('recurrence', 'none') === $option)>{{ __('club.events.recurrence.' . $option) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="series_until" type="date" :label="__('club.events.field.series_until')" :value="old('series_until')" />
        @endunless
        @if ($inSeries)
            <x-select-field name="scope" :label="__('club.events.field.scope')" span="2">
                <option value="this" @selected(old('scope', 'this') === 'this')>{{ __('club.events.scope.this') }}</option>
                <option value="future" @selected(old('scope') === 'future')>{{ __('club.events.scope.future') }}</option>
            </x-select-field>
        @endif
    </x-form-group>

    <x-form-group :legend="__('club.events.field.occupancy')" icon="how_to_reg" tone="warning" cols="3" :description="__('club.events.hint.capacity')">
        <x-input-field name="max_participants" type="number" min="1" max="9999" :label="__('club.events.field.max_participants')" :value="old('max_participants', $event?->max_participants)" />
        <x-input-field name="registration_lead_hours" type="number" min="0" max="8760" :label="__('club.events.field.registration_lead_hours')" :value="old('registration_lead_hours', $details?->registration_lead_hours)" />
        <x-input-field name="cancellation_lead_hours" type="number" min="0" max="8760" :label="__('club.events.field.cancellation_lead_hours')" :value="old('cancellation_lead_hours', $details?->cancellation_lead_hours)" />
    </x-form-group>
</x-modal>

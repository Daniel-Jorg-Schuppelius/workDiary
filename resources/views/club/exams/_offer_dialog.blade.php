{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _offer_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Prüfungsangebot anlegen/bearbeiten (in #entry-modal geladen). Variablen:
  $offer|null (mit targetGrades), $event|null, $systems (mit grades), $groups,
  $departments, $users, $rooms, $visibilities. Die Regelversion wird bei Anlage eingefroren.
--}}
@php
    $isEdit = $offer !== null;
    $details = $event?->clubDetails;
    $formTz = $event?->timezone ?: \App\Support\Tz::current();
    $inFormTz = static fn ($value): ?string => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->setTimezone($formTz)->format('Y-m-d\TH:i');
    $selectedGroups = collect(old('club_group_ids', $event?->clubGroups->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedGrades = collect(old('target_grade_ids', $offer?->targetGrades->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
    $selectedExaminers = collect(old('examiner_user_ids', collect($offer?->examinerIds() ?? [])->map(fn ($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id))->all()))->map(fn ($v) => (string) $v)->all();
    $selectedSystem = old('club_grading_system_id', $offer?->club_grading_system_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubGradingSystem::class, $offer->club_grading_system_id) : '');
    $selectedDepartment = old('club_department_id', $details?->club_department_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubDepartment::class, $details->club_department_id) : '');
    $selectedLeader = old('leader_user_id', $event?->responsibleUser?->sqid ?? '');
    $selectedRoom = old('room_id', $event?->rooms->first()?->sqid ?? '');
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.exams.action.create_offer')"
    :eyebrow="__('club.exams.title.index')"
    icon="workspace_premium"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.exams.update', $offer) : route('club.exams.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.exams.card.offer')" icon="workspace_premium" tone="primary" cols="2" :description="$isEdit ? __('club.exams.hint.version_frozen') : __('club.exams.hint.offer')">
        <x-input-field name="title" :label="__('club.events.field.title')" required maxlength="200" span="2" :value="old('title', $event?->title)" />
        <x-select-field name="club_grading_system_id" :label="__('club.grading.field.system')" required :disabled="$isEdit">
            <option value="">–</option>
            @foreach ($systems as $system)
                <option value="{{ $system->sqid }}" @selected((string) $selectedSystem === $system->sqid)>{{ $system->name }} · {{ $system->discipline }}</option>
            @endforeach
        </x-select-field>
        @if ($isEdit)
            <input type="hidden" name="club_grading_system_id" value="{{ $selectedSystem }}">
        @endif
        <x-select-field name="target_grade_ids[]" id="target_grade_ids" :label="__('club.exams.field.target_grades')" multiple required :hint="__('club.exams.hint.target_grades')">
            @foreach ($systems as $system)
                <optgroup label="{{ $system->name }}">
                    @foreach ($system->grades as $grade)
                        <option value="{{ $grade->sqid }}" @selected(in_array($grade->sqid, $selectedGrades, true))>{{ $grade->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>
        <x-select-field name="examiner_user_ids[]" id="examiner_user_ids" :label="__('club.exams.field.examiners')" multiple span="2" :hint="__('club.exams.hint.examiners')">
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected(in_array($user->sqid, $selectedExaminers, true))>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="5000" span="2" :value="old('description', $event?->description)" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $offer?->notes)" />
    </x-form-group>

    <x-form-group :legend="__('club.events.field.groups')" icon="diversity_3" tone="primary" cols="2" :description="__('club.exams.hint.visibility')">
        <x-select-field name="visibility" :label="__('club.events.field.visibility')" required>
            @foreach ($visibilities as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $details?->visibility->value ?? 'groups') === $visibility->value)>{{ $visibility->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="club_department_id" :label="__('club.field.department')">
            <option value="">{{ __('club.label.no_department') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->sqid }}" @selected((string) $selectedDepartment === $department->sqid)>{{ $department->name }}</option>
            @endforeach
        </x-select-field>
        <div class="col-span-2 flex flex-wrap gap-3">
            @foreach ($groups as $group)
                <x-checkbox-field name="club_group_ids[]" :id="'ex-group-' . $group->sqid" :value="$group->sqid" :label="$group->name" :checked="in_array($group->sqid, $selectedGroups, true)" :with-hidden="false" />
            @endforeach
        </div>
    </x-form-group>

    <x-form-group :legend="__('club.events.field.period')" icon="schedule" tone="info" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      type="datetime-local" from-name="started_at" to-name="ended_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('started_at', $inFormTz($event?->started_at))"
                      :to="old('ended_at', $inFormTz($event?->ended_at))" />
        <input type="hidden" name="timezone" value="{{ $formTz }}">
        <x-select-field name="room_id" :label="__('club.events.field.room')">
            <option value="">–</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->sqid }}" @selected((string) $selectedRoom === $room->sqid)>{{ $room->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="leader_user_id" :label="__('club.events.field.leader')">
            <option value="">–</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) $selectedLeader === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="max_participants" type="number" min="1" max="9999" :label="__('club.events.field.max_participants')" :value="old('max_participants', $event?->max_participants)" />
        <div class="grid grid-cols-2 gap-2">
            <x-input-field name="registration_lead_hours" type="number" min="0" max="8760" :label="__('club.events.field.registration_lead_hours')" :value="old('registration_lead_hours', $details?->registration_lead_hours)" />
            <x-input-field name="cancellation_lead_hours" type="number" min="0" max="8760" :label="__('club.events.field.cancellation_lead_hours')" :value="old('cancellation_lead_hours', $details?->cancellation_lead_hours)" />
        </div>
    </x-form-group>
</x-modal>

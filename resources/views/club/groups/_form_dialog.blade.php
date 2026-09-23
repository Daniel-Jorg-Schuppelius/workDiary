{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Gruppen-Dialog (in #entry-modal geladen).
  Variablen: $group (ClubGroup|null), $departments, $leaders (Collection<User>)
--}}
@php
    $isEdit = $group !== null;
    $selectedDepartment = old('club_department_id', $group?->department?->sqid ?? '');
    $selectedLeader = old('leader_user_id', $group?->leader?->sqid ?? '');
@endphp

<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.action.create_group')"
    :eyebrow="__('club.title.groups')"
    icon="diversity_3"
    tone="primary"
    :action="$isEdit ? route('club.groups.update', $group) : route('club.groups.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.action.create_group')">

    <x-form-group :legend="__('club.card.master_data')" icon="diversity_3" tone="primary" cols="2">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" :value="old('name', $group?->name)" />
        <x-select-field name="club_department_id" :label="__('club.field.department')">
            <option value="">{{ __('club.label.no_department') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->sqid }}" @selected((string) $selectedDepartment === $department->sqid)>{{ $department->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="leader_user_id" :label="__('club.field.leader')">
            <option value="">–</option>
            @foreach ($leaders as $leader)
                <option value="{{ $leader->sqid }}" @selected((string) $selectedLeader === $leader->sqid)>{{ $leader->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="admission_mode" :label="__('club.field.admission_mode')" required>
            @foreach (\App\Enums\Club\ClubAdmissionMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('admission_mode', $group?->admission_mode->value ?? 'leader') === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="2000" span="2" :value="old('description', $group?->description)" />
    </x-form-group>

    <x-form-group :legend="__('club.card.criteria')" icon="rule" tone="primary" cols="3" :description="__('club.hint.criteria')">
        <x-input-field name="min_age" type="number" min="0" max="120" :label="__('club.field.min_age')" :value="old('min_age', $group?->min_age)" />
        <x-input-field name="max_age" type="number" min="0" max="120" :label="__('club.field.max_age')" :value="old('max_age', $group?->max_age)" />
        <x-input-field name="max_members" type="number" min="1" max="9999" :label="__('club.field.max_members')" :value="old('max_members', $group?->max_members)" />
        <x-input-field name="criteria_note" :label="__('club.field.criteria_note')" maxlength="255" span="3" :value="old('criteria_note', $group?->criteria_note)" />
        <x-input-field name="discipline" :label="__('club.field.discipline')" maxlength="60" :value="old('discipline', $group?->discipline)" :hint="__('club.grading.hint.group_discipline')" />
        @php $selectedSystem = old('club_grading_system_id', $group?->club_grading_system_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubGradingSystem::class, $group->club_grading_system_id) : ''); @endphp
        <x-select-field name="club_grading_system_id" :label="__('club.grading.field.system')" :hint="__('club.grading.hint.group_grades')">
            <option value="">–</option>
            @foreach ($gradingSystems as $system)
                <option value="{{ $system->sqid }}" @selected((string) $selectedSystem === $system->sqid)>{{ $system->name }}</option>
            @endforeach
        </x-select-field>
        <div class="grid grid-cols-2 gap-2">
            @foreach (['min_grade_id' => 'min_grade', 'max_grade_id' => 'max_grade'] as $field => $labelKey)
                @php $selectedGrade = old($field, $group?->{$field} ? \App\Support\Sqid::encode(\App\Models\Club\ClubGrade::class, $group->{$field}) : ''); @endphp
                <x-select-field :name="$field" :label="__('club.grading.field.' . $labelKey)">
                    <option value="">–</option>
                    @foreach ($gradingSystems as $system)
                        <optgroup label="{{ $system->name }}">
                            @foreach ($system->grades as $grade)
                                <option value="{{ $grade->sqid }}" @selected((string) $selectedGrade === $grade->sqid)>{{ $grade->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-select-field>
            @endforeach
        </div>
        {{-- Mannschaft (MVP-852): Sportartenprofil eigen oder von der Abteilung, Altersklasse als Etikett. --}}
        <x-checkbox-field name="is_team" :label="__('club.teams.field.is_team')" :checked="(bool) old('is_team', $group?->is_team ?? false)" />
        @php $selectedProfile = old('club_sport_profile_id', $group?->club_sport_profile_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubSportProfile::class, $group->club_sport_profile_id) : ''); @endphp
        <x-select-field name="club_sport_profile_id" :label="__('club.teams.field.profile')" :hint="__('club.teams.hint.group_profile')">
            <option value="">{{ __('club.teams.label.profile_from_department') }}</option>
            @foreach ($profiles as $profile)
                <option value="{{ $profile->sqid }}" @selected((string) $selectedProfile === $profile->sqid)>{{ $profile->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="age_class" :label="__('club.teams.field.age_class')" maxlength="20" :value="old('age_class', $group?->age_class)" :hint="__('club.teams.hint.age_class')" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $group?->is_active ?? true)" span="3" />
    </x-form-group>
</x-modal>

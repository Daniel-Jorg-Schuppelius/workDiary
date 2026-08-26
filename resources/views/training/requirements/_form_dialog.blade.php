{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog Pflichtzuordnung (Feature 145). Variablen: $requirement
  (TrainingRequirement|null), $courses, $teams
--}}
@php
    $isEdit = $requirement !== null;
    $courseSqid = \App\Support\Sqid::encodeOrNull(\App\Models\Training\TrainingCourse::class, $requirement?->training_course_id);
    $isTeam = $requirement?->subject_kind === \App\Enums\Training\TrainingRequirementSubject::Team;
    $teamSqid = $isTeam ? \App\Support\Sqid::encode(\App\Models\Team::class, (int) $requirement->subject_key) : null;
    $roleValue = $isTeam ? null : $requirement?->subject_key;
@endphp

<x-modal
    :title="$isEdit ? __('training.action.edit') : __('training.action.create_requirement')"
    :eyebrow="__('training.title.requirements')"
    icon="grid_view"
    tone="primary"
    :action="$isEdit ? route('training.requirements.update', $requirement) : route('training.requirements.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('training.action.save') : __('training.action.create_requirement')">

    <x-form-group :legend="__('training.field.subject')" :description="__('training.hint.no_second_guard')" icon="grid_view" tone="primary" cols="2">
        <x-select-field name="training_course_id" :label="__('training.field.course')" required span="2">
            <option value="">—</option>
            @foreach ($courses as $course)
                <option value="{{ $course->sqid }}" @selected((string) old('training_course_id', $courseSqid) === $course->sqid)>{{ $course->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="subject_kind" :label="__('training.field.subject_kind')" required>
            @foreach (\App\Enums\Training\TrainingRequirementSubject::cases() as $subject)
                <option value="{{ $subject->value }}" @selected(old('subject_kind', $requirement?->subject_kind?->value ?? 'role') === $subject->value)>{{ $subject->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="first_due_days" type="number" min="0" max="3650" :label="__('training.field.first_due_days')" required :value="old('first_due_days', $requirement?->first_due_days ?? 30)" />
        <x-select-field name="subject_role" :label="__('training.field.subject_role')">
            <option value="">—</option>
            @foreach (\App\Enums\User\UserRole::cases() as $role)
                <option value="{{ $role->value }}" @selected((string) old('subject_role', $roleValue) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="subject_team_id" :label="__('training.field.subject_team')">
            <option value="">—</option>
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected((string) old('subject_team_id', $teamSqid) === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="is_active" :label="__('training.field.is_active')" :checked="(bool) old('is_active', $requirement?->is_active ?? true)" />
        <x-input-field name="note" :label="__('training.field.notes')" maxlength="255" :value="old('note', $requirement?->note)" />
    </x-form-group>
</x-modal>

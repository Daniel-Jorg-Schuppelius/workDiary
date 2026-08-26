{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Unterweisung mit Teilnehmer-Mehrfachauswahl.
  Variablen: $instruction (SafetyInstruction|null), $users (Collection id/name),
             $assessments (Collection id/assessment_no/version/area),
             $courses (Collection TrainingCourse mit versions — Feature 145)
--}}
@php
    $isEdit = $instruction !== null;
    $selectedParticipants = $isEdit
        ? $instruction->participants->map(fn ($p) => \App\Support\Sqid::encode(\App\Models\User::class, $p->user_id))->all()
        : [];
    $instructorSqid = \App\Support\Sqid::encode(\App\Models\User::class, $instruction?->instructor_user_id ?? auth()->id());
    $assessmentSqid = \App\Support\Sqid::encodeOrNull(\App\Models\Safety\HazardAssessment::class, $instruction?->hazard_assessment_id);
    $courseSqid = \App\Support\Sqid::encodeOrNull(\App\Models\Training\TrainingCourse::class, $instruction?->training_course_id);
    $courseVersionSqid = \App\Support\Sqid::encodeOrNull(\App\Models\Training\TrainingCourseVersion::class, $instruction?->training_course_version_id);
@endphp

<x-modal
    :title="$isEdit ? __('safety.register.action.edit') : __('safety.register.action.create_instruction')"
    :eyebrow="__('safety.register.title.instructions')"
    icon="school"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('safety.instructions.update', $instruction) : route('safety.instructions.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('safety.register.action.save') : __('safety.register.action.create_instruction')">

    <x-form-group :legend="__('safety.register.field.topic')" icon="school" tone="primary" cols="2">
        <x-input-field name="topic" :label="__('safety.register.field.topic')" required minlength="2" maxlength="180" span="2" :value="old('topic', $instruction?->topic)" />
        <x-input-field name="held_on" type="date" :label="__('safety.register.field.held_on')" required :value="old('held_on', $instruction?->held_on?->toDateString() ?? now()->toDateString())" />
        <x-input-field name="repeat_interval_months" type="number" min="1" max="120" :label="__('safety.register.field.repeat_interval_months')" :value="old('repeat_interval_months', $instruction?->repeat_interval_months ?? 12)" />
        <x-select-field name="instructor_user_id" :label="__('safety.register.field.instructor')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('instructor_user_id', $instructorSqid) === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="hazard_assessment_id" :label="__('safety.register.field.assessment')">
            <option value="">—</option>
            @foreach ($assessments as $assessment)
                <option value="{{ $assessment->sqid }}" @selected((string) old('hazard_assessment_id', $assessmentSqid) === $assessment->sqid)>{{ $assessment->displayNo() }} — {{ $assessment->area }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('safety.register.field.notes')" rows="2" maxlength="10000" span="2" :value="old('notes', $instruction?->notes)" />
    </x-form-group>

    {{-- Feature 145: erst der Kursbezug macht die Teilnahme zum Trainings-Nachweis. --}}
    <x-form-group :legend="__('training.field.course')" :description="__('training.hint.instruction_course')" icon="school" tone="info" cols="2">
        <x-select-field name="training_course_id" :label="__('training.field.course')">
            <option value="">—</option>
            @foreach ($courses as $course)
                <option value="{{ $course->sqid }}" @selected((string) old('training_course_id', $courseSqid) === $course->sqid)>{{ $course->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="training_course_version_id" :label="__('training.field.version')">
            <option value="">—</option>
            @foreach ($courses as $course)
                @foreach ($course->versions as $version)
                    <option value="{{ $version->sqid }}" @selected((string) old('training_course_version_id', $courseVersionSqid) === $version->sqid)>{{ $course->title }} · {{ $version->displayLabel() }}</option>
                @endforeach
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('safety.register.field.participants')" icon="groups" tone="success" cols="1">
        <x-user-checklist name="participant_ids" :users="$users" :selected="$selectedParticipants" />
        @error('participant_ids')
            <p class="text-xs text-error">{{ $message }}</p>
        @enderror
    </x-form-group>
</x-modal>

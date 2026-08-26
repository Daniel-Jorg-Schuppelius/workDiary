{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog für ein einzelnes Schulungs-Soll außerhalb der Matrix
  (Feature 145). Variablen: $users, $courses
--}}
<x-modal
    :title="__('training.action.create_assignment')"
    :eyebrow="__('training.title.assignments')"
    icon="fact_check"
    tone="primary"
    :action="route('training.assignments.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('training.action.create_assignment')">

    <x-form-group :legend="__('training.title.assignments')" :description="__('training.hint.proof_in_register')" icon="fact_check" tone="primary" cols="2">
        <x-select-field name="user_id" :label="__('training.field.user')" required span="2">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('user_id') === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="training_course_id" :label="__('training.field.course')" required>
            <option value="">—</option>
            @foreach ($courses as $course)
                <option value="{{ $course->sqid }}" @selected((string) old('training_course_id') === $course->sqid)>{{ $course->title }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="due_at" type="date" :label="__('training.field.due_at')" :value="old('due_at')" />
    </x-form-group>
</x-modal>

{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _waiver_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Versuchsfreigabe (Feature 149, MVP-785): ein weiterer Versuch trotz
  Versuchsgrenze oder Sperrfrist — genau einmal, mit Begründung.
  Variablen: $course, $enrollment, $quizzes.
--}}
<x-modal
    :title="__('learning.action.grant_waiver')"
    :eyebrow="$enrollment->learnerName()"
    icon="replay"
    tone="warning"
    :action="route('learning.courses.enrollments.attempt-waiver', [$course, $enrollment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.grant_waiver')">

    <x-form-group :legend="__('learning.field.quiz')" icon="quiz" tone="warning" cols="1">
        <x-select-field name="quiz_id" :label="__('learning.field.quiz')" required>
            @foreach ($quizzes as $quiz)
                <option value="{{ $quiz->sqid }}" @selected(old('quiz_id') === $quiz->sqid)>{{ $quiz->title }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="reason" :label="__('learning.field.reason')" required minlength="2" maxlength="255" rows="2"
                          :hint="__('learning.help.waiver')" :value="old('reason')" />
    </x-form-group>
</x-modal>

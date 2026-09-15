{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _question_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Frageformular des Prüfungseditors (MVP-738/779/782) — Anlegen (landet im
  Katalog UND in dieser Prüfung) und Bearbeiten. Variablen: $course, $unit,
  $question (null beim Anlegen), $lines, $categories.
--}}
@php
    $editing = $question !== null;
    $formAction = $editing
        ? route('learning.courses.units.quiz.questions.update', [$course, $unit, $question])
        : route('learning.courses.units.quiz.questions.store', [$course, $unit]);
@endphp
<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif
    @include('learning.courses._question_fields', ['question' => $question, 'lines' => $lines ?? '', 'categories' => $categories])
    <div class="mt-3 flex justify-end gap-2">
        @if ($editing)
            <x-icon-btn icon="close" tone="ghost" size="sm"
                        :href="route('learning.courses.units.quiz.edit', [$course, $unit])"
                        show-label>{{ __('learning.action.cancel_edit') }}</x-icon-btn>
            <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.save') }}</x-icon-btn>
        @else
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.add_question') }}</x-icon-btn>
        @endif
    </div>
</form>

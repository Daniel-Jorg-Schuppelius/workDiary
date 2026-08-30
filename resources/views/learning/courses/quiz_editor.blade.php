{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : quiz_editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prüfungs-Editor (Feature 149, MVP-738). Antwortoptionen werden als
  Zeilenliste gepflegt: eine Zeile je Option, ein führendes `*` markiert
  die richtige. Kompakt, ohne JavaScript und damit CSP-fest.
--}}
@extends('layouts.app')
@section('title', __('learning.field.quiz'))
@section('nav-title', __('learning.field.quiz'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$unit->title" :badge="$course->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.show', $course)"
                            show-label>{{ __('learning.action.back_to_course') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="quiz" class="text-muted" /> {{ __('learning.field.questions') }}
                </h3>

                @forelse ($quiz?->questions ?? [] as $question)
                    <div class="mb-3 rounded-box border border-base-300 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">{{ $question->prompt }}</p>
                                <p class="mt-1 text-xs text-muted">
                                    {{ $question->kind->label() }} · {{ $question->points }} {{ __('learning.field.score') }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('learning.courses.units.quiz.questions.destroy', [$course, $unit, $question]) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('learning.action.remove_block')" />
                            </form>
                        </div>
                        @if ($question->options->isNotEmpty())
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($question->options as $option)
                                    <li class="flex items-center gap-2">
                                        <x-icon name="{{ $option->is_correct ? 'check_circle' : 'radio_button_unchecked' }}"
                                                class="{{ $option->is_correct ? 'text-success' : 'text-muted' }}" />
                                        <span>{{ $option->label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <x-empty-state icon="quiz" :title="__('learning.empty.questions')" compact />
                @endforelse
            </x-card>

            @if ($quiz)
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="add_box" class="text-muted" /> {{ __('learning.action.add_question') }}
                    </h3>
                    <form method="POST" action="{{ route('learning.courses.units.quiz.questions.store', [$course, $unit]) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <x-form-group :legend="__('learning.field.question')" icon="help" tone="primary" cols="2">
                            <x-select-field name="kind" :label="__('learning.field.question_kind')" required>
                                @foreach (\App\Enums\Learning\LearningQuestionKind::cases() as $case)
                                    <option value="{{ $case->value }}" @selected(old('kind', 'single') === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field name="points" type="number" min="1" max="100" :label="__('learning.field.score')" required :value="old('points', 1)" />
                            <x-textarea-field name="prompt" :label="__('learning.field.prompt')" rows="2" span="2" required maxlength="2000" :value="old('prompt')" />
                            <x-textarea-field name="options" :label="__('learning.field.options')" rows="4" span="2" maxlength="5000"
                                              :hint="__('learning.help.options_lines')" :value="old('options')" />
                            <x-textarea-field name="explanation" :label="__('learning.field.explanation')" rows="2" span="2" maxlength="2000" :value="old('explanation')" />
                            <x-checkbox-field name="partial_credit" :label="__('learning.help.partial_credit')" :checked="(bool) old('partial_credit')" />
                            <x-checkbox-field name="case_sensitive" :label="__('learning.field.case_sensitive')" :checked="(bool) old('case_sensitive')" />
                            <div class="sm:col-span-2">
                                {{-- Nur für die Bildmarkierung: ohne Bild gibt es
                                     nichts zu markieren. --}}
                                <label class="label" for="question-image"><span class="label-text">{{ __('learning.field.question_image') }}</span></label>
                                <input type="file" id="question-image" name="image" accept="image/*"
                                       class="file-input file-input-bordered file-input-sm w-full">
                                <p class="mt-1 text-xs text-muted">{{ __('learning.help.question_image') }}</p>
                            </div>
                        </x-form-group>
                        <div class="mt-3 flex justify-end">
                            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.add_question') }}</x-icon-btn>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.quiz') }}</h3>
                <form method="POST" action="{{ route('learning.courses.units.quiz.update', [$course, $unit]) }}">
                    @csrf
                    @method('PUT')
                    <x-form-group :legend="__('learning.field.quiz')" icon="quiz" tone="primary" cols="1">
                        <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" :value="old('title', $quiz?->title ?? $unit->title)" />
                        <x-input-field name="pass_percent" type="number" min="1" max="100" :label="__('learning.field.pass_percent')" required :value="old('pass_percent', $quiz?->pass_percent ?? 80)" />
                        <x-input-field name="time_limit_minutes" type="number" min="1" max="600" :label="__('learning.field.time_limit_minutes')" :value="old('time_limit_minutes', $quiz?->time_limit_minutes)" />
                        <x-input-field name="max_attempts" type="number" min="0" max="50" :label="__('learning.field.max_attempts')" required :value="old('max_attempts', $quiz?->max_attempts ?? 3)" />
                        <x-input-field name="retry_wait_hours" type="number" min="0" max="8760" :label="__('learning.field.retry_wait_hours')" required :value="old('retry_wait_hours', $quiz?->retry_wait_hours ?? 0)" />
                        <x-input-field name="questions_per_attempt" type="number" min="1" max="500" :label="__('learning.field.questions_per_attempt')" :value="old('questions_per_attempt', $quiz?->questions_per_attempt)" />
                        <x-select-field name="feedback_mode" :label="__('learning.field.feedback_mode')" required>
                            @foreach (\App\Enums\Learning\LearningFeedbackMode::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('feedback_mode', $quiz?->feedback_mode?->value ?? 'end') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-select-field>
                        <x-checkbox-field name="shuffle_questions" :label="__('learning.field.shuffle_questions')" :checked="(bool) old('shuffle_questions', $quiz?->shuffle_questions ?? true)" />
                        <x-checkbox-field name="shuffle_answers" :label="__('learning.field.shuffle_answers')" :checked="(bool) old('shuffle_answers', $quiz?->shuffle_answers ?? true)" />
                        <x-checkbox-field name="show_solutions" :label="__('learning.field.show_solutions')" :checked="(bool) old('show_solutions', $quiz?->show_solutions ?? false)" />
                    </x-form-group>
                    <div class="mt-3 flex justify-end">
                        <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.save') }}</x-icon-btn>
                    </div>
                </form>
                <p class="mt-3 text-xs text-muted">{{ __('learning.help.quiz_snapshot') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

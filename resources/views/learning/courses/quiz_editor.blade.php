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
                    <div class="mb-3 rounded-box border {{ isset($editing) && $editing->is($question) ? 'border-primary' : 'border-base-300' }} p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">{{ $question->prompt }}</p>
                                <p class="mt-1 text-xs text-muted">
                                    {{ $question->kind->label() }} · {{ $question->points }} {{ __('learning.field.score') }}
                                    @if ($question->category)
                                        · {{ $question->category->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('learning.courses.units.quiz.questions.move', [$course, $unit, $question]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <x-icon-btn icon="arrow_upward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_up')" :disabled="$loop->first" />
                                </form>
                                <form method="POST" action="{{ route('learning.courses.units.quiz.questions.move', [$course, $unit, $question]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <x-icon-btn icon="arrow_downward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_down')" :disabled="$loop->last" />
                                </form>
                                <x-icon-btn icon="edit" tone="ghost" size="xs"
                                            :href="route('learning.courses.units.quiz.questions.edit', [$course, $unit, $question])"
                                            :label="__('learning.action.edit_question')" />
                                <form method="POST" action="{{ route('learning.courses.units.quiz.questions.duplicate', [$course, $unit, $question]) }}">
                                    @csrf
                                    <x-icon-btn icon="content_copy" tone="ghost" size="xs" type="submit" :label="__('learning.action.duplicate_question')" />
                                </form>
                                <form method="POST" action="{{ route('learning.courses.units.quiz.questions.destroy', [$course, $unit, $question]) }}"
                                      data-confirm-dialog data-confirm-message="{{ __('learning.confirm.detach_question') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="playlist_remove" tone="ghost" size="xs" type="submit" :label="__('learning.action.detach_question')" />
                                </form>
                            </div>
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
                        <x-icon name="library_add" class="text-muted" /> {{ __('learning.field.catalog') }}
                    </h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-icon-btn icon="library_add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('learning.courses.units.quiz.catalog', [$course, $unit])"
                                    show-label>{{ __('learning.action.from_catalog') }}</x-icon-btn>
                        <x-icon-btn icon="quiz" tone="ghost" size="sm"
                                    :href="route('learning.questions.index')"
                                    show-label>{{ __('learning.nav.questions') }}</x-icon-btn>
                        @can(\App\Enums\User\Permission::LearningGrade->value)
                            <x-icon-btn icon="insights" tone="ghost" size="sm"
                                        :href="route('learning.courses.units.quiz.statistics', [$course, $unit])"
                                        show-label>{{ __('learning.action.quiz_statistics') }}</x-icon-btn>
                        @endcan
                    </div>

                    {{-- Ziehregeln (MVP-782): je Versuch N zufällige Katalogfragen
                         einer Kategorie — zusätzlich zur festen Liste. --}}
                    <h4 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('learning.field.draw_rules') }}</h4>
                    @forelse ($quiz->drawRules as $rule)
                        <div class="mb-1 flex items-center justify-between gap-2 rounded-box border border-base-300 px-3 py-1.5 text-sm">
                            <span>{{ __('learning.field.draw_rule_line', ['count' => $rule->count, 'category' => $rule->category?->name ?? '–']) }}</span>
                            <form method="POST" action="{{ route('learning.courses.units.quiz.draw-rules.destroy', [$course, $unit, $rule]) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('learning.action.remove_block')" />
                            </form>
                        </div>
                    @empty
                        <p class="mb-2 text-xs text-muted">{{ __('learning.empty.draw_rules') }}</p>
                    @endforelse
                    <form method="POST" action="{{ route('learning.courses.units.quiz.draw-rules.store', [$course, $unit]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                        @csrf
                        <x-select-field name="category_id" :label="__('learning.field.category')" required class="w-48">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->sqid }}" @selected(old('category_id') === $cat->sqid)>{{ $cat->name }}</option>
                            @endforeach
                        </x-select-field>
                        <x-input-field name="count" type="number" min="1" max="500" :label="__('learning.field.draw_count')" required :value="old('count', 3)" class="w-28" />
                        <x-icon-btn icon="add" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.add_draw_rule') }}</x-icon-btn>
                    </form>
                </x-card>
            @endif

            @if ($quiz && ($aiQuestions ?? false))
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="auto_awesome" class="text-muted" /> {{ __('learning.field.ai_draft') }}
                    </h3>
                    @if (session('aiDraft'))
                        <label class="label" for="ai-draft"><span class="label-text">{{ __('learning.field.ai_draft') }}</span></label>
                        <textarea id="ai-draft" class="textarea textarea-bordered w-full font-mono text-xs" rows="10" readonly>{{ session('aiDraft') }}</textarea>
                    @endif
                    <form method="POST" action="{{ route('learning.courses.units.quiz.ai-draft', [$course, $unit]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <x-input-field name="count" type="number" min="1" max="20" :label="__('learning.field.question_count')" :value="old('count', 5)" class="w-32" />
                        <x-icon-btn icon="auto_awesome" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.ai_questions') }}</x-icon-btn>
                    </form>
                    <p class="mt-2 text-xs text-muted">{{ __('learning.help.ai_questions') }}</p>
                </x-card>
            @endif

            @if ($quiz)
                @php
                    $editingQuestion = $editing ?? null;
                @endphp
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="{{ $editingQuestion ? 'edit' : 'add_box' }}" class="text-muted" />
                        {{ $editingQuestion ? __('learning.field.editing_question') : __('learning.action.add_question') }}
                    </h3>
                    @include('learning.courses._question_form', [
                        'course' => $course,
                        'unit' => $unit,
                        'question' => $editingQuestion,
                        'lines' => $editingLines ?? '',
                        'categories' => $categories,
                    ])
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
                        <x-input-field name="questions_per_attempt_percent" type="number" min="1" max="100" :label="__('learning.field.questions_per_attempt_percent')" :hint="__('learning.help.questions_per_attempt_percent')" :value="old('questions_per_attempt_percent', $quiz?->questions_per_attempt_percent)" />
                        <x-input-field name="pass_points" type="number" min="1" max="100000" :label="__('learning.field.pass_points')" :hint="__('learning.help.pass_points')" :value="old('pass_points', $quiz?->pass_points)" />
                        <x-select-field name="feedback_mode" :label="__('learning.field.feedback_mode')" required>
                            @foreach (\App\Enums\Learning\LearningFeedbackMode::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('feedback_mode', $quiz?->feedback_mode?->value ?? 'end') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-select-field>
                        <x-checkbox-field name="shuffle_questions" :label="__('learning.field.shuffle_questions')" :checked="(bool) old('shuffle_questions', $quiz?->shuffle_questions ?? true)" />
                        <x-checkbox-field name="shuffle_answers" :label="__('learning.field.shuffle_answers')" :checked="(bool) old('shuffle_answers', $quiz?->shuffle_answers ?? true)" />
                        <x-checkbox-field name="show_solutions" :label="__('learning.field.show_solutions')" :checked="(bool) old('show_solutions', $quiz?->show_solutions ?? false)" />
                        <x-select-field name="display_mode" :label="__('learning.field.display_mode')">
                            <option value="all" @selected(old('display_mode', $quiz?->display_mode ?? 'all') === 'all')>{{ __('learning.field.display_mode_all') }}</option>
                            <option value="single" @selected(old('display_mode', $quiz?->display_mode ?? 'all') === 'single')>{{ __('learning.field.display_mode_single') }}</option>
                        </x-select-field>
                        <x-checkbox-field name="allow_back" :label="__('learning.field.allow_back')" :checked="(bool) old('allow_back', $quiz?->allow_back ?? true)" />
                        <x-checkbox-field name="allow_skip" :label="__('learning.field.allow_skip')" :checked="(bool) old('allow_skip', $quiz?->allow_skip ?? true)" />
                        <x-checkbox-field name="require_all_answered" :label="__('learning.field.require_all_answered')" :checked="(bool) old('require_all_answered', $quiz?->require_all_answered ?? false)" />
                        @php
                            $resultLines = collect($quiz?->result_messages ?? [])->map(fn ($m) => ($m['from_percent'] ?? 0) . ': ' . ($m['text'] ?? ''))->implode("\n");
                        @endphp
                        <x-textarea-field name="result_messages" :label="__('learning.field.result_messages')" rows="3" maxlength="5000"
                                          :hint="__('learning.help.result_messages')" :value="old('result_messages', $resultLines)" />
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

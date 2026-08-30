{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : quiz_result.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ergebnis eines Prüfungsversuchs (Feature 149, MVP-738). Solange ein
  Aufsatz auf Bewertung wartet, steht das Gesamtergebnis nicht fest.
--}}
@extends('layouts.app')
@section('title', $quiz->title)
@section('nav-title', $quiz->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$enrollment->course?->title"
                        :badge="$attempt->passed === null ? __('learning.field.pending_grading') : ($attempt->passed ? __('learning.field.passed') : __('learning.field.not_passed'))"
                        :badgeTone="$attempt->passed === null ? 'warning' : ($attempt->passed ? 'success' : 'error')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.my.show', $enrollment)"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($attempt->questions() as $index => $question)
                @php
                    $answer = $answers[$question['id']] ?? null;
                    $showSolution = $quiz->show_solutions
                        && $quiz->feedback_mode !== \App\Enums\Learning\LearningFeedbackMode::None;
                @endphp
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold">{{ $index + 1 }}. {{ $question['prompt'] }}</h3>
                        @if ($answer?->is_correct === true)
                            <x-status-badge tone="success" size="sm">{{ $answer->effectivePoints() }} / {{ $question['points'] }}</x-status-badge>
                        @elseif ($answer?->is_correct === false)
                            <x-status-badge tone="error" size="sm">{{ $answer->effectivePoints() }} / {{ $question['points'] }}</x-status-badge>
                        @else
                            <x-status-badge tone="warning" size="sm">{{ __('learning.field.pending_grading') }}</x-status-badge>
                        @endif
                    </div>

                    @if ($showSolution && ! empty($question['explanation']))
                        <p class="mt-2 text-sm text-base-content/80">{{ $question['explanation'] }}</p>
                    @endif
                    @if ($answer?->correction_note)
                        <p class="mt-2 text-sm"><span class="font-medium">{{ __('learning.field.correction') }}:</span> {{ $answer->correction_note }}</p>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.score') }}</h3>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.attempt')" :value="$attempt->attempt_no" />
                    <x-detail-grid.row :label="__('learning.field.score')" :value="$attempt->score_points . ' / ' . $attempt->max_points" />
                    <x-detail-grid.row :label="__('learning.field.pass_percent')" :value="$attempt->score_percent . ' % (≥ ' . $quiz->pass_percent . ' %)'" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('learning.help.quiz_snapshot') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

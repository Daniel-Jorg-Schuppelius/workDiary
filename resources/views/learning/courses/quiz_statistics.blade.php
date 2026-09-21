{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : quiz_statistics.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prüfungsstatistik (Feature 149, MVP-785): Versuche mit Ergebnis, Quoten je
  Frage ab n ≥ MIN_GROUP, Zeitbedarf. Variablen: $course, $unit, $quiz,
  $attempts, $stats, $minGroup.
--}}
@extends('layouts.app')
@section('title', __('learning.title.quiz_statistics'))
@section('nav-title', $quiz->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->title" :badge="$quiz->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.units.quiz.edit', [$course, $unit])"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="history" class="text-muted" /> {{ __('learning.field.attempts') }}
                </h3>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.learner') }}</th>
                            <th class="text-center">{{ __('learning.field.attempt') }}</th>
                            <th>{{ __('learning.field.started_at') }}</th>
                            <th class="text-right">{{ __('learning.field.score') }}</th>
                            <th>{{ __('learning.field.status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($attempts as $attempt)
                        <tr class="hover">
                            <td>{{ $attempt->enrollment?->learnerName() ?? '–' }}</td>
                            <td class="text-center">#{{ $attempt->attempt_no }}</td>
                            <td class="text-sm">{{ $attempt->started_at?->orgTz()->translatedFormat('d.m.Y H:i') }}</td>
                            <td class="text-right font-mono text-sm">{{ $attempt->submitted_at ? ($attempt->score_percent ?? 0) . ' %' : '–' }}</td>
                            <td>
                                @if ($attempt->submitted_at === null)
                                    <x-status-badge tone="ghost" size="sm">{{ __('learning.field.open') }}</x-status-badge>
                                @elseif ($attempt->passed === null)
                                    <x-status-badge tone="warning" size="sm">{{ __('learning.field.pending_grading') }}</x-status-badge>
                                @elseif ($attempt->passed)
                                    <x-status-badge tone="success" size="sm">{{ __('learning.field.passed') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="error" size="sm">{{ __('learning.field.failed') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <x-icon-btn icon="visibility" tone="ghost" size="xs"
                                            :href="route('learning.grading.attempts.show', $attempt)"
                                            :label="__('learning.action.view_attempt')" />
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="history" :colspan="6" :title="__('learning.empty.attempts')" compact />
                    @endforelse
                </x-table>
                <x-pagination :paginator="$attempts" :standing="false" framed />
            </x-card>

            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="quiz" class="text-muted" /> {{ __('learning.field.question_stats') }}
                </h3>
                <p class="mb-2 text-xs text-muted">{{ __('learning.help.min_group', ['count' => $minGroup]) }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.question') }}</th>
                            <th class="text-right">{{ __('learning.field.answered') }}</th>
                            <th class="text-right">{{ __('learning.field.error_rate') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($stats['questions'] as $row)
                        <tr class="hover">
                            <td class="text-sm">{{ $row['prompt'] }}</td>
                            <td class="text-right text-sm">{{ $row['answered'] }}</td>
                            <td class="text-right text-sm">{{ $row['error_rate'] !== null ? $row['error_rate'] . ' %' : '–' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon="quiz" :colspan="3" :title="__('learning.empty.attempts')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.summary') }}</h3>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.attempts')" :value="$stats['attempts']" />
                    <x-detail-grid.row :label="__('learning.field.submitted')" :value="$stats['submitted']" />
                    <x-detail-grid.row :label="__('learning.field.pass_rate')" :value="$stats['pass_rate'] !== null ? $stats['pass_rate'] . ' %' : '–'" />
                    <x-detail-grid.row :label="__('learning.field.median_duration')" :value="$stats['median_seconds'] !== null ? intdiv($stats['median_seconds'], 60) . ' ' . __('learning.field.minutes_short') : '–'" />
                </x-detail-grid>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

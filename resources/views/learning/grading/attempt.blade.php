{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : attempt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prüfungsakte für Prüfende (Feature 149, MVP-785): der eingefrorene
  Snapshot mit den gegebenen Antworten, Punkten und Korrekturen. Jeder
  Aufruf ist protokolliert. Variablen: $attempt, $enrollment, $quiz, $answers.
--}}
@extends('layouts.app')
@section('title', __('learning.title.attempt_record'))
@section('nav-title', __('learning.title.attempt_record'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$quiz?->title"
                        :badge="$attempt->passed === null ? __('learning.field.pending_grading') : ($attempt->passed ? __('learning.field.passed') : __('learning.field.failed'))"
                        :badgeTone="$attempt->passed === null ? 'warning' : ($attempt->passed ? 'success' : 'error')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('learning.grading.index')" show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($attempt->questions() as $index => $question)
                @php
                    $answer = $answers[$question['id']] ?? null;
                    $payload = is_array($answer?->payload) ? $answer->payload : [];
                    $optionLabels = collect($question['options'] ?? [])->keyBy('id');
                    $given = [];
                    foreach ((array) ($payload['option_ids'] ?? []) as $id) {
                        $given[] = (string) ($optionLabels[(int) $id]['label'] ?? $id);
                    }
                    if (isset($payload['text'])) { $given[] = (string) $payload['text']; }
                    foreach ((array) ($payload['gaps'] ?? []) as $gap) { $given[] = (string) $gap; }
                    foreach ((array) ($payload['order'] ?? []) as $id) { $given[] = (string) ($optionLabels[(int) $id]['label'] ?? $id); }
                    foreach ((array) ($payload['pairs'] ?? []) as $left => $right) { $given[] = ($optionLabels[(int) $left]['label'] ?? $left) . ' → ' . ($optionLabels[(int) $right]['label'] ?? $right); }
                    if (isset($payload['spot'])) { $given[] = __('learning.field.hotspot_choice') . ' ' . ((int) $payload['spot'] + 1); }
                    if (isset($payload['x'], $payload['y'])) { $given[] = round((float) $payload['x']) . '% / ' . round((float) $payload['y']) . '%'; }
                    foreach ((array) ($payload['matrix'] ?? []) as $row => $col) { $given[] = (($question['settings']['rows'][$row]['label'] ?? $row) . ' → ' . ($question['settings']['columns'][$col] ?? $col)); }
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
                    <p class="mt-2 text-sm">
                        <span class="font-medium">{{ __('learning.field.answer') }}:</span>
                        {{ $given !== [] ? implode(' · ', $given) : '–' }}
                    </p>
                    @if ($answer?->flagged)
                        <p class="mt-1 text-xs text-muted">{{ __('learning.action.mark_question') }}</p>
                    @endif
                    @if ($answer?->correction_note)
                        <p class="mt-2 text-sm"><span class="font-medium">{{ __('learning.field.correction') }}:</span> {{ $answer->correction_note }}</p>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.attempt') }}</h3>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.learner')" :value="$enrollment->learnerName()" />
                    <x-detail-grid.row :label="__('learning.field.attempt')" :value="'#' . $attempt->attempt_no" />
                    <x-detail-grid.row :label="__('learning.field.started_at')" :value="$attempt->started_at?->orgTz()->translatedFormat('d.m.Y H:i') ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.submitted_at')" :value="$attempt->submitted_at?->orgTz()->translatedFormat('d.m.Y H:i') ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.score')" :value="$attempt->score_points . ' / ' . $attempt->max_points . ' (' . ($attempt->score_percent ?? 0) . ' %)'" />
                    <x-detail-grid.row :label="__('learning.field.device')" :value="trim(($attempt->client_ip ?? '') . ' ' . \Illuminate\Support\Str::limit((string) $attempt->user_agent, 60)) ?: '–'" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('learning.help.attempt_record') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

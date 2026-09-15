{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : quiz_attempt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prüfungsversuch (Feature 149, MVP-738). Gestellt werden die Fragen aus
  dem eingefrorenen Snapshot des Versuchs — nicht die aktuellen Fragen.
  Prüfungen sind bewusst online-pflichtig.
--}}
@extends('layouts.app')
@section('title', $quiz->title)
@section('nav-title', $quiz->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$enrollment->course?->title"
                        :badge="__('learning.field.attempt') . ' ' . $attempt->attempt_no">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.my.show', $enrollment)"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @php
        $questions = $attempt->questions();
        $drafts = $drafts ?? collect();
        // Ablaufsteuerung (MVP-783): fragenweise, Zurück/Überspringen, Zwischenspeicher, Countdown.
        $runnerOptions = [
            'total' => count($questions),
            'single' => $quiz->isSingleQuestionMode(),
            'allowBack' => (bool) $quiz->allow_back,
            'allowSkip' => (bool) $quiz->allow_skip,
            'expiresAt' => $attempt->expires_at?->toIso8601String(),
            'saveUrl' => route('learning.my.quiz.answer', [$enrollment, $attempt]),
            'answered' => $drafts->filter(fn ($d) => $d->payload !== null)->keys()->map(fn ($k) => (int) $k)->values()->all(),
            'flagged' => $drafts->filter(fn ($d) => (bool) $d->flagged)->keys()->map(fn ($k) => (int) $k)->values()->all(),
            'ids' => array_map(fn (array $q): int => (int) $q['id'], $questions),
        ];
    @endphp
    <form method="POST" enctype="multipart/form-data" action="{{ route('learning.my.quiz.submit', [$enrollment, $attempt]) }}"
          x-data="quizRunner(@js($runnerOptions))" data-quiz-runner>
        @csrf
        <div class="space-y-4">
            @if ($errors->any())
                <div class="alert alert-error text-sm" role="alert">
                    <x-icon name="error" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if ($attempt->expires_at)
                {{-- Die verbleibende Zeit ist eine Statusinformation, keine
                     Dekoration — sonst erfährt sie niemand, der die Seite
                     vorlesen lässt. Der Countdown läuft im Browser; die
                     Frist selbst prüft der Server. --}}
                <div class="alert alert-warning text-sm" role="status" aria-live="polite">
                    <x-icon name="timer" />
                    <span>
                        {{ __('learning.field.remaining_time') }}:
                        <span class="font-mono" x-text="remaining">{{ $attempt->expires_at->translatedFormat('H:i') }}</span>
                    </span>
                </div>
            @endif

            {{-- Fragenübersicht mit Sprungmarken: beantwortet / gemerkt / offen. --}}
            <nav class="flex flex-wrap gap-1" aria-label="{{ __('learning.field.question_overview') }}" data-quiz-overview>
                @foreach ($questions as $index => $question)
                    <button type="button" class="btn btn-xs" :class="overviewClass({{ (int) $question['id'] }})"
                            @click="goTo({{ $index }})"
                            :aria-current="isCurrent({{ $index }}) ? 'step' : null">
                        {{ $index + 1 }}
                    </button>
                @endforeach
                <span class="ml-2 self-center text-xs text-muted" x-text="progressLabel()"></span>
            </nav>

            @foreach ($questions as $index => $question)
                @php
                    $kind = \App\Enums\Learning\LearningQuestionKind::tryFrom($question['kind'] ?? '');
                    $draft = $drafts->get($question['id']);
                    $draftPayload = is_array($draft?->payload) ? $draft->payload : [];
                @endphp
                <x-card x-show="isVisible({{ $index }})" data-quiz-question="{{ $question['id'] }}"
                        @change="save({{ (int) $question['id'] }})">
                    {{-- Die Frage ist die Beschriftung ihrer Antwortfelder
                         (WCAG 1.3.1/3.3.2). Ohne fieldset/legend liest ein
                         Screenreader die Optionen vor, ohne zu sagen, wozu
                         sie gehören. --}}
                    <fieldset>
                        <legend class="mb-1 text-sm font-semibold">
                            {{ $index + 1 }}. {{ $question['prompt'] }}
                        </legend>
                        <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-muted">
                            <span>{{ $kind?->label() }} · {{ $question['points'] }} {{ __('learning.field.score') }}</span>
                            <label class="flex items-center gap-1">
                                <input type="checkbox" class="checkbox checkbox-xs" name="flags[{{ $question['id'] }}]" value="1"
                                       data-quiz-flag="{{ $question['id'] }}" @checked((bool) ($draft?->flagged ?? false))>
                                {{ __('learning.action.mark_question') }}
                            </label>
                        </div>
                        @if (! empty($question['settings']['hint']))
                            <details class="mb-3 text-sm">
                                <summary class="cursor-pointer text-primary">{{ __('learning.field.hint') }}</summary>
                                <p class="mt-1 text-base-content/80">{{ $question['settings']['hint'] }}</p>
                            </details>
                        @endif

                    @switch($kind)
                        @case(\App\Enums\Learning\LearningQuestionKind::Single)
                        @case(\App\Enums\Learning\LearningQuestionKind::TrueFalse)
                            @foreach ($question['options'] as $option)
                                <label class="mb-2 flex items-center gap-2 text-sm">
                                    <input type="radio" class="radio radio-sm"
                                           name="answers[{{ $question['id'] }}][option_ids][]"
                                           value="{{ $option['id'] }}" @checked(in_array((string) $option['id'], array_map('strval', (array) ($draftPayload['option_ids'] ?? [])), true))>
                                    <span>{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Multiple)
                            @foreach ($question['options'] as $option)
                                <label class="mb-2 flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="checkbox checkbox-sm"
                                           name="answers[{{ $question['id'] }}][option_ids][]"
                                           value="{{ $option['id'] }}" @checked(in_array((string) $option['id'], array_map('strval', (array) ($draftPayload['option_ids'] ?? [])), true))>
                                    <span>{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Essay)
                            @php $essayKind = (string) ($question['settings']['submission_kind'] ?? 'text'); @endphp
                            @if ($essayKind !== 'upload')
                                <label class="sr-only" for="answer-{{ $question['id'] }}">{{ __('learning.field.answer') }}</label>
                                <textarea id="answer-{{ $question['id'] }}" class="textarea textarea-bordered w-full" rows="5"
                                          name="answers[{{ $question['id'] }}][text]"
                                          maxlength="5000">{{ $draftPayload['text'] ?? '' }}</textarea>
                            @endif
                            @if ($essayKind !== 'text')
                                {{-- Aufsatz-Upload (MVP-793): die Datei hängt nach der Abgabe an der Antwort. --}}
                                <label class="label" for="answer-file-{{ $question['id'] }}"><span class="label-text">{{ __('learning.field.essay_file') }}</span></label>
                                <input type="file" id="answer-file-{{ $question['id'] }}" class="file-input file-input-bordered file-input-sm w-full"
                                       name="answers[{{ $question['id'] }}][file]">
                            @endif
                            <p class="mt-1 text-xs text-muted">{{ __('learning.field.pending_grading') }}</p>
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Assessment)
                            {{-- Selbsteinschätzung (MVP-793): keine richtige Antwort, die Stufe ist der Wert. --}}
                            @foreach (array_values((array) ($question['settings']['scale'] ?? [])) as $levelIndex => $levelLabel)
                                <label class="mb-2 flex items-center gap-2 text-sm">
                                    <input type="radio" class="radio radio-sm"
                                           name="answers[{{ $question['id'] }}][level]"
                                           value="{{ $levelIndex + 1 }}" @checked((string) ($draftPayload['level'] ?? '') === (string) ($levelIndex + 1))>
                                    <span>{{ $levelLabel }}</span>
                                </label>
                            @endforeach
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Cloze)
                            {{-- Ein Feld je Lücke: der Bewerter liest `gaps`
                                 und vergibt Teilpunkte je gefüllter Lücke. --}}
                            @php $gapCount = max(1, count($question['settings']['gaps'] ?? [])); @endphp
                            <div class="space-y-2">
                                @for ($gap = 0; $gap < $gapCount; $gap++)
                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="w-16 shrink-0 text-muted">{{ __('learning.field.gap') }} {{ $gap + 1 }}</span>
                                        <input type="text" class="input input-bordered input-sm w-full"
                                               aria-label="{{ __('learning.field.gap') }} {{ $gap + 1 }}"
                                               name="answers[{{ $question['id'] }}][gaps][]" maxlength="200"
                                               value="{{ $draftPayload['gaps'][$gap] ?? '' }}">
                                    </label>
                                @endfor
                            </div>
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Sort)
                            {{-- Je Rang eine Auswahl. Bewusst ohne Ziehen-und-Ablegen:
                                 das Ergebnis ist genau die Reihenfolge, die der
                                 Bewerter als `order` erwartet, und es geht ohne Skript. --}}
                            <div class="space-y-2">
                                @foreach ($question['options'] as $rank => $unused)
                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="w-16 shrink-0 text-muted">{{ $rank + 1 }}.</span>
                                        <select class="select select-bordered select-sm w-full"
                                                aria-label="{{ __('learning.field.rank_position', ['rank' => $rank + 1]) }}"
                                                name="answers[{{ $question['id'] }}][order][]">
                                            <option value="">—</option>
                                            @foreach ($question['options'] as $option)
                                                <option value="{{ $option['id'] }}" @selected((string) ($draftPayload['order'][$rank] ?? '') === (string) $option['id'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Matching)
                            {{-- Paare teilen sich einen `match_key`: die erste
                                 Option der Gruppe steht links, die zweite ist
                                 die gesuchte Zuordnung. --}}
                            @php
                                $groups = collect($question['options'])->groupBy('match_key');
                                $lefts = $groups->map(fn ($g) => $g->first())->values();
                                $rights = $groups->map(fn ($g) => $g->get(1))->filter()->values();
                            @endphp
                            <div class="space-y-2">
                                @foreach ($lefts as $left)
                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="w-40 shrink-0">{{ $left['label'] }}</span>
                                        <select class="select select-bordered select-sm w-full"
                                                aria-label="{{ __('learning.field.match_for', ['label' => $left['label']]) }}"
                                                name="answers[{{ $question['id'] }}][pairs][{{ $left['id'] }}]">
                                            <option value="">—</option>
                                            @foreach ($rights as $right)
                                                <option value="{{ $right['id'] }}" @selected((string) ($draftPayload['pairs'][$left['id']] ?? '') === (string) $right['id'])>{{ $right['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Hotspot)
                            {{-- Bildmarkierung. Die Koordinaten sind Prozent der
                                 Bildkante, damit die Antwort auf Telefon und
                                 Bildschirm dieselbe ist. Ohne Skript wäre der Typ
                                 nicht bedienbar — deshalb zusätzlich eine
                                 Auswahlliste der Bereiche für die Tastatur
                                 (WCAG 2.1.1). --}}
                            @php $spots = $question['settings']['hotspots'] ?? []; @endphp
                            @if (! empty($question['settings']['image_attachment_id']))
                                <img src="{{ route('learning.my.questions.image', [$enrollment->sqid, $attempt->sqid, $question['id']]) }}"
                                     alt="{{ $question['prompt'] }}"
                                     class="max-w-full rounded-box border border-base-300"
                                     data-hotspot-image="{{ $question['id'] }}">
                            @endif
                            <input type="hidden" name="answers[{{ $question['id'] }}][x]" data-hotspot-x="{{ $question['id'] }}" value="{{ $draftPayload['x'] ?? '' }}">
                            <input type="hidden" name="answers[{{ $question['id'] }}][y]" data-hotspot-y="{{ $question['id'] }}" value="{{ $draftPayload['y'] ?? '' }}">
                            <label class="sr-only" for="hotspot-{{ $question['id'] }}">{{ __('learning.field.hotspot_choice') }}</label>
                            <select class="select select-bordered select-sm mt-2 w-full"
                                    id="hotspot-{{ $question['id'] }}"
                                    name="answers[{{ $question['id'] }}][spot]">
                                <option value="">{{ __('learning.field.hotspot_pick') }}</option>
                                @foreach ($spots as $i => $spot)
                                    <option value="{{ $i }}" @selected((string) ($draftPayload['spot'] ?? '') === (string) $i)>{{ $spot['label'] ?? ($i + 1) }}</option>
                                @endforeach
                            </select>
                            @break

                        @case(\App\Enums\Learning\LearningQuestionKind::Matrix)
                            {{-- Matrix: je Zeile eine Spalte. Dieselbe Spalte darf
                                 mehrfach vorkommen — das unterscheidet sie von der
                                 Zuordnung. --}}
                            @php
                                $rows = $question['settings']['rows'] ?? [];
                                $columns = $question['settings']['columns'] ?? [];
                            @endphp
                            <div class="space-y-2">
                                @foreach ($rows as $rowIndex => $row)
                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="w-48 shrink-0">{{ $row['label'] ?? '' }}</span>
                                        <select class="select select-bordered select-sm w-full"
                                                aria-label="{{ $row['label'] ?? '' }}"
                                                name="answers[{{ $question['id'] }}][matrix][{{ $rowIndex }}]">
                                            <option value="">—</option>
                                            @foreach ($columns as $colIndex => $column)
                                                <option value="{{ $colIndex }}" @selected((string) ($draftPayload['matrix'][$rowIndex] ?? '') === (string) $colIndex)>{{ $column }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @default
                            <label class="sr-only" for="answer-{{ $question['id'] }}">{{ __('learning.field.answer') }}</label>
                            <input type="text" id="answer-{{ $question['id'] }}"
                                   class="input input-bordered w-full"
                                   name="answers[{{ $question['id'] }}][text]" maxlength="500"
                                   value="{{ $draftPayload['text'] ?? '' }}">
                    @endswitch
                    </fieldset>
                    @if ($quiz->isSingleQuestionMode())
                        <div class="mt-3 flex flex-wrap justify-between gap-2" data-quiz-nav>
                            <div class="flex gap-2">
                                @if ($quiz->allow_back)
                                    <x-icon-btn icon="arrow_back" tone="ghost" size="sm" type="button" @click="previous()" x-show="hasPrevious()" show-label>{{ __('learning.action.previous_question') }}</x-icon-btn>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                @if ($quiz->allow_skip)
                                    <x-icon-btn icon="skip_next" tone="ghost" size="sm" type="button" @click="skip()" x-show="hasNext()" show-label>{{ __('learning.action.skip_question') }}</x-icon-btn>
                                @endif
                                <x-icon-btn icon="arrow_forward" tone="outline" size="sm" type="button" @click="next()" x-show="hasNext()" show-label>{{ __('learning.action.next_question') }}</x-icon-btn>
                            </div>
                        </div>
                    @endif
                </x-card>
            @endforeach

            <div class="flex items-center justify-end gap-3">
                @if ($quiz->require_all_answered)
                    <span class="text-xs text-muted">{{ __('learning.help.require_all_answered') }}</span>
                @endif
                <x-icon-btn icon="done_all" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.submit_attempt') }}</x-icon-btn>
            </div>
        </div>
    </form>
</x-page-shell>

{{-- Bildmarkierung: Klickposition in PROZENT der Bildkante erfassen. Pixel
     wären von der Anzeigegröße abhängig — auf dem Telefon käme eine andere
     Antwort heraus als am Bildschirm. Wer nicht klicken kann, wählt die
     Fläche aus der Liste; beide Wege führen zum selben Ergebnis. --}}
<script @cspNonce>
(function () {
    'use strict';

    document.querySelectorAll('[data-hotspot-image]').forEach(function (image) {
        const id = image.getAttribute('data-hotspot-image');
        const fieldX = document.querySelector('[data-hotspot-x="' + id + '"]');
        const fieldY = document.querySelector('[data-hotspot-y="' + id + '"]');

        if (!fieldX || !fieldY) { return; }

        image.style.cursor = 'crosshair';

        image.addEventListener('click', function (event) {
            const box = image.getBoundingClientRect();

            if (box.width === 0 || box.height === 0) { return; }

            fieldX.value = String(((event.clientX - box.left) / box.width) * 100);
            fieldY.value = String(((event.clientY - box.top) / box.height) * 100);

            image.style.outline = '3px solid currentColor';
        });
    });
})();
</script>
@endsection

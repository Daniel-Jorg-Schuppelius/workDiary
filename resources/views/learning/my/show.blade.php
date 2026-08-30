{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lern-Player (Feature 149): Einheiten der eigenen Einschreibung mit
  Abschluss-Bestätigung. Der Stoffstand ist die Kursversion der
  Einschreibung — er ändert sich nicht unter laufenden Teilnehmern.
--}}
@extends('layouts.app')
@section('title', $course->title)
@section('nav-title', $course->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->subtitle ?? __('learning.subtitle.my')"
                        :badge="$enrollment->status->label()"
                        :badgeTone="$enrollment->status->tone()">
            <x-slot:actions>
                @if ($enrollment->status === \App\Enums\Learning\LearningEnrollmentStatus::Completed && $enrollment->course?->certificate_enabled)
                    {{-- Der Ausdruck ist eine Kopie; maßgeblich bleibt der
                         Datensatz mit seinem Prüfcode. --}}
                    <x-icon-btn icon="workspace_premium" tone="primary" size="sm"
                                :href="route('learning.my.certificate', $enrollment->sqid)"
                                show-label>{{ __('learning.action.certificate_pdf') }}</x-icon-btn>
                @endif
                {{-- Offline-Ablage nur auf ausdrückliche Anforderung: der Stoff
                     landet im Gerätespeicher, und das soll niemand unbemerkt
                     tun. Beim Abmelden wird er wieder gelöscht. --}}
                <x-icon-btn icon="download_for_offline" tone="ghost" size="sm" type="button"
                            data-offline-course="{{ $enrollment->sqid }}"
                            data-offline-course-url="{{ route('learning.my.offline', $enrollment->sqid) }}"
                            show-label>{{ __('learning.action.save_offline') }}</x-icon-btn>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.my.index')"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($course->objectives)
                <x-card>
                    <h3 class="mb-2 text-sm font-semibold">{{ __('learning.field.objectives') }}</h3>
                    <p class="text-sm text-base-content/80">{{ $course->objectives }}</p>
                </x-card>
            @endif

            @php
                // WCAG 3.1.2: liegt keine freigegebene Übersetzung vor, steht
                // hier fremdsprachiger Text. Ohne lang-Auszeichnung liest ihn
                // ein Screenreader in der Seitensprache vor — unverständlich.
                $sourceLocale = $enrollment->organization?->locale ?? config('app.locale');
            @endphp
            @foreach ($course->units as $unit)
                @php
                    $isDone = in_array($unit->id, $completedUnitIds, true);
                    $needsLangHint = ! isset($translated[$unit->id]) && $sourceLocale !== app()->getLocale();
                @endphp
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="flex items-center gap-2 text-sm font-semibold">
                                <x-icon name="{{ $isDone ? 'check_circle' : 'radio_button_unchecked' }}" class="{{ $isDone ? 'text-success' : 'text-muted' }}" />
                                <span @if ($needsLangHint) lang="{{ $sourceLocale }}" @endif>{{ $translated[$unit->id]['title'] ?? $unit->title }}</span>
                            </h3>
                            <p class="mt-1 text-xs text-muted">
                                {{ $unit->kind->label() }}
                                @if ($unit->section) · {{ $unit->section->title }} @endif
                                @if ($unit->duration_minutes) · {{ $unit->duration_minutes }} {{ __('learning.field.minutes_short') }} @endif
                            </p>
                        </div>
                        @unless ($isDone)
                            @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Event && $unit->event)
                                @php $participation = $eventParticipations[$unit->event_id] ?? null; @endphp
                                @if ($participation?->status === \App\Enums\Event\ParticipantStatus::Waitlisted)
                                    <x-status-badge tone="neutral" size="sm">{{ __('learning.field.waitlist') }}</x-status-badge>
                                @elseif ($participation?->status === \App\Enums\Event\ParticipantStatus::Accepted)
                                    <form method="POST" action="{{ route('learning.my.events.cancel', [$enrollment, $unit]) }}">
                                        @csrf
                                        <x-icon-btn icon="event_busy" tone="ghost" size="sm" type="submit" show-label>{{ __('learning.action.cancel_event') }}</x-icon-btn>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('learning.my.events.register', [$enrollment, $unit]) }}">
                                        @csrf
                                        <x-icon-btn icon="event_available" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.register_event') }}</x-icon-btn>
                                    </form>
                                @endif
                            @elseif ($unit->kind === \App\Enums\Learning\LearningUnitKind::Assignment && $unit->assignment)
                                @php $submission = $submissions[$unit->assignment->id] ?? null; @endphp
                                @if ($submission?->isPending())
                                    <x-status-badge tone="info" size="sm">{{ __('learning.field.pending_grading') }}</x-status-badge>
                                @endif
                            @elseif ($unit->kind === \App\Enums\Learning\LearningUnitKind::Quiz && $unit->quiz)
                                {{-- Prüfungen laufen über einen Versuch, nicht über
                                     eine Bestätigung — online-pflichtig. --}}
                                <form method="POST" action="{{ route('learning.my.quiz.start', [$enrollment, $unit->quiz]) }}">
                                    @csrf
                                    <x-icon-btn icon="quiz" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.start_attempt') }}</x-icon-btn>
                                </form>
                            @elseif ($unit->kind === \App\Enums\Learning\LearningUnitKind::Scorm && $unit->scormPackage)
                                {{-- SCORM meldet den Abschluss selbst — hier gibt es
                                     keine Bestätigung von Hand. --}}
                                <x-icon-btn icon="play_circle" tone="primary" size="sm"
                                            :href="route('learning.my.scorm.play', [$enrollment, $unit])"
                                            show-label>{{ __('learning.action.open_scorm') }}</x-icon-btn>
                            @else
                                {{-- Offline abhakbar, aber NUR ohne Online-Pflicht:
                                     Prüfungen und Aufgaben tragen das Attribut
                                     nicht, und der Server lehnt sie zusätzlich ab
                                     (eine offline erzeugte Prüfungsakte wäre nicht
                                     manipulationssicher). --}}
                                <form method="POST" action="{{ route('learning.my.units.complete', [$enrollment, $unit]) }}"
                                      @unless ($unit->kind->requiresOnline())
                                          data-offline-sync="learning.unit-complete"
                                          data-sync-payload-enrollment="{{ $enrollment->sqid }}"
                                          data-sync-payload-unit="{{ $unit->sqid }}"
                                      @endunless>
                                    @csrf
                                    <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.complete_unit') }}</x-icon-btn>
                                </form>
                            @endif
                        @endunless
                    </div>

                    {{-- Alle Blockarten, nicht nur Text: was hier fehlte, war
                         im Kurs unsichtbar (Bilder, Hinweise, Checklisten …).
                         Freigegebene Übersetzungen ersetzen nur die Texte;
                         Medien und Fragen bleiben unberührt. --}}
                    @php
                        $blocks = $unit->blocks();
                        foreach (($translated[$unit->id]['blocks'] ?? []) as $t) {
                            $i = (int) ($t['index'] ?? -1);
                            if (isset($blocks[$i])) {
                                $blocks[$i] = array_replace($blocks[$i], array_intersect_key($t, ['text' => 1, 'items' => 1]));
                            }
                        }
                    @endphp
                    <div @if ($needsLangHint) lang="{{ $sourceLocale }}" @endif>
                    @include('learning._blocks', [
                        'blocks' => $blocks,
                        'mediaState' => $mediaState,
                        'mediaUrl' => fn (int $id): ?string => ($a = $unit->attachments->firstWhere('id', $id))
                            ? route('learning.my.units.media', [$enrollment->sqid, $unit->sqid, $a->sqid])
                            : null,
                    ])
                    </div>

                    @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Event && $unit->event)
                        <p class="mt-3 text-sm text-base-content/80">
                            <x-icon name="calendar_month" class="text-muted" />
                            {{ $unit->event->started_at?->translatedFormat('d.m.Y H:i') }}
                            @if ($unit->event->ended_at) – {{ $unit->event->ended_at->translatedFormat('H:i') }} @endif
                        </p>
                        <p class="mt-1 text-xs text-muted">{{ __('learning.help.event_attendance') }}</p>
                    @endif

                    @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Assignment && $unit->assignment)
                        @php $submission = $submissions[$unit->assignment->id] ?? null; @endphp
                        <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $unit->assignment->instructions }}</p>

                        @if ($submission?->feedback)
                            <div class="mt-3 rounded-box border border-warning/40 bg-warning/10 p-3 text-sm">
                                <span class="font-medium">{{ __('learning.field.feedback') }}:</span>
                                {{ $submission->feedback }}
                            </div>
                        @endif

                        @if ($submission === null || $submission->status->allowsSubmission())
                            <form method="POST" action="{{ route('learning.my.assignments.submit', [$enrollment, $unit->assignment]) }}"
                                  enctype="multipart/form-data" class="mt-3">
                                @csrf
                                @if ($unit->assignment->requiresText())
                                    <x-textarea-field name="body" :label="__('learning.field.submission')" rows="4" maxlength="20000"
                                                      :value="old('body', $submission?->body)" />
                                @endif
                                @if ($unit->assignment->requiresFile())
                                    <label class="label" for="submission-files-{{ $unit->id }}">
                                        <span class="label-text">{{ __('learning.field.submission_files') }}</span>
                                    </label>
                                    <input type="file" id="submission-files-{{ $unit->id }}" name="files[]" multiple
                                           class="file-input file-input-bordered file-input-sm w-full">
                                    <p class="mt-1 text-xs text-muted">{{ __('learning.help.submission_files', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
                                @endif
                                @if ($submission?->attachments->isNotEmpty())
                                    <ul class="mt-2 space-y-1 text-sm">
                                        @foreach ($submission->attachments as $attachment)
                                            <li>
                                                <a class="link" href="{{ route('learning.my.submissions.file', [$enrollment->sqid, $submission->sqid, $attachment->sqid]) }}">
                                                    {{ $attachment->original_name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="mt-2 flex justify-end">
                                    <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.submit_assignment') }}</x-icon-btn>
                                </div>
                            </form>
                        @elseif ($submission->status === \App\Enums\Learning\LearningSubmissionStatus::Graded)
                            <p class="mt-3 text-sm">
                                {{ __('learning.field.score') }}:
                                <span class="font-mono">{{ $submission->points_awarded }} / {{ $unit->assignment->points }}</span>
                                ({{ $submission->score_percent }} %)
                            </p>
                        @endif
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="space-y-4">
            {{-- Lernzeit: außerhalb der Arbeitszeit entsteht daraus ein
                 Arbeitszeitnachweis, innerhalb wird nichts doppelt gezählt. --}}
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="timer" class="text-muted" /> {{ __('learning.field.learning_time') }}
                </h3>
                @if ($openSession)
                    <p class="mb-3 text-sm">
                        {{ __('learning.field.time_running') }}
                        <span class="font-mono">{{ $openSession->started_at?->translatedFormat('H:i') }}</span>
                    </p>
                    <form method="POST" action="{{ route('learning.my.time.stop', $enrollment) }}">
                        @csrf
                        <x-icon-btn icon="stop_circle" tone="warning" size="sm" type="submit" show-label>{{ __('learning.action.stop_time') }}</x-icon-btn>
                    </form>
                    <p class="mt-2 text-xs text-muted">{{ __('learning.help.heartbeat') }}</p>
                @else
                    <form method="POST" action="{{ route('learning.my.time.start', $enrollment) }}">
                        @csrf
                        <x-icon-btn icon="play_circle" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.start_time') }}</x-icon-btn>
                    </form>
                @endif

                <x-detail-grid class="mt-3">
                    <x-detail-grid.row :label="__('learning.field.time_inside')" :value="intdiv($timeTotals['inside'], 60) . ' ' . __('learning.field.minutes_short')" />
                    <x-detail-grid.row :label="__('learning.field.time_outside')" :value="intdiv($timeTotals['outside'], 60) . ' ' . __('learning.field.minutes_short')" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('learning.help.learning_time') }}</p>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.course') }}</h3>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.status')" :value="$enrollment->status->label()" />
                    <x-detail-grid.row :label="__('learning.field.due_at')" :value="$enrollment->due_at?->translatedFormat('d.m.Y') ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.version')" :value="$enrollment->learning_course_version_id ? 'v' . ($enrollment->courseVersion?->version ?? '?') : '–'" />
                    <x-detail-grid.row :label="__('learning.field.time_policy')" :value="$course->time_policy->label()" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('learning.help.time_policy') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>

@if ($openSession)
    {{-- Lebenszeichen, solange der Tab sichtbar ist. Ohne dieses Signal
         zählte ein offener Tab, den niemand benutzt, als gearbeitete Zeit —
         bei Lernzeit außerhalb der Arbeitszeit wäre das eine falsche Angabe
         in den Zeitkonten. --}}
    <script @cspNonce>
    (function () {
        'use strict';

        const endpoint = @json(route('learning.my.time.heartbeat', $enrollment->sqid));
        const token = document.querySelector('meta[name="csrf-token"]');

        function ping() {
            if (document.visibilityState !== 'visible') { return; }

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                },
            }).catch(function () { /* Ein verpasster Puls kürzt nur die Zeit. */ });
        }

        ping();
        setInterval(ping, 120000);
        document.addEventListener('visibilitychange', ping);
    })();
    </script>
@endif
@endsection

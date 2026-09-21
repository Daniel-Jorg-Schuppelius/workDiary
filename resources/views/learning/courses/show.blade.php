{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kursakte (Feature 149): Struktur (Abschnitte/Einheiten), Versionen und
  der Freigabe-Weg. Nach der Freigabe ist der Inhalt eingefroren —
  Korrekturen laufen über eine Folgeversion.
--}}
@extends('layouts.app')
@section('title', $course->title)
@section('nav-title', $course->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->subtitle ?? __('learning.subtitle.courses')"
                        :badge="$course->status->label()"
                        :badgeTone="$course->status->tone()">
            <x-slot:actions>
                <x-collection-add-button :item="$course" />
                @if ($aiOutline ?? false)
                    <x-icon-btn icon="auto_awesome" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('learning.courses.ai-outline.create', $course)"
                                show-label>{{ __('learning.action.ai_outline') }}</x-icon-btn>
                @endif
                @if ($canEditContent)
                    <x-icon-btn icon="playlist_add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('learning.courses.units.create', $course)"
                                show-label>{{ __('learning.action.add_unit') }}</x-icon-btn>
                    <x-icon-btn icon="segment" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('learning.courses.sections.create', $course)"
                                show-label>{{ __('learning.action.add_section') }}</x-icon-btn>
                @endif
                @if ($canEditMeta)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('learning.courses.edit', $course)"
                                show-label>{{ __('learning.action.edit') }}</x-icon-btn>
                @endif
                @can('create', \App\Models\Learning\LearningCourse::class)
                    <form method="POST" action="{{ route('learning.courses.duplicate', $course) }}"
                          data-confirm-dialog data-confirm-message="{{ __('learning.confirm.duplicate') }}">
                        @csrf
                        <x-icon-btn icon="content_copy" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.duplicate') }}</x-icon-btn>
                    </form>
                @endcan
                @can(\App\Enums\User\Permission::LearningGrade->value)
                    <x-icon-btn icon="grading" tone="outline" size="sm"
                                :href="route('learning.courses.gradebook.show', $course)"
                                show-label>{{ __('learning.action.gradebook') }}</x-icon-btn>
                @endcan
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.index')"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="list" class="text-muted" /> {{ __('learning.field.structure') }}
                </h3>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.unit') }}</th>
                            <th>{{ __('learning.field.unit_kind') }}</th>
                            <th class="text-center">{{ __('learning.field.duration_minutes') }}</th>
                            <th class="text-center">{{ __('learning.field.points') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($course->units as $unit)
                        <tr class="hover">
                            <td class="font-medium">
                                {{ $unit->title }}
                                @if ($unit->section)
                                    <span class="text-xs text-muted">· {{ $unit->section->title }}</span>
                                @endif
                                @unless ($unit->is_mandatory)
                                    <x-status-badge tone="ghost" size="sm">{{ __('learning.field.optional') }}</x-status-badge>
                                @endunless
                                @if ($unit->is_preview)
                                    <x-status-badge tone="info" size="sm" outline>{{ __('learning.badge.preview') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-sm">
                                <x-status-badge :tone="$unit->kind->tone()" size="sm" outline>{{ $unit->kind->label() }}</x-status-badge>
                            </td>
                            <td class="text-center text-sm">{{ $unit->duration_minutes ?? '–' }}</td>
                            <td class="text-center text-sm">{{ $unit->points }}</td>
                            <td class="text-right">
                                @if ($canEditContent)
                                    <div class="flex justify-end gap-1">
                                        @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Quiz)
                                            <x-icon-btn icon="quiz" tone="ghost" size="xs"
                                                        :href="route('learning.courses.units.quiz.edit', [$course, $unit])"
                                                        :label="__('learning.action.edit_quiz')" />
                                        @endif
                                        @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Assignment)
                                            <x-icon-btn icon="assignment" tone="ghost" size="xs"
                                                        :href="route('learning.courses.units.assignment.edit', [$course, $unit])"
                                                        :label="__('learning.action.edit_assignment')" />
                                        @endif
                                        @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Event && $unit->event_id)
                                            {{-- Arbeitsmittel für die Kursleitung: QR-Check-in
                                                 plus Unterschriftenspalte auf Papier. --}}
                                            <x-icon-btn icon="fact_check" tone="ghost" size="xs"
                                                        :href="route('learning.courses.units.attendance-list', [$course, $unit])"
                                                        :label="__('learning.action.attendance_list')" />
                                        @endif
                                        <x-icon-btn icon="edit_note" tone="ghost" size="xs"
                                                    :href="route('learning.courses.units.edit', [$course, $unit])"
                                                    :label="__('learning.action.edit_unit')" />
                                        <form method="POST" action="{{ route('learning.courses.units.move', [$course, $unit]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <x-icon-btn icon="arrow_upward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_up')" :disabled="$loop->first" />
                                        </form>
                                        <form method="POST" action="{{ route('learning.courses.units.move', [$course, $unit]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <x-icon-btn icon="arrow_downward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_down')" :disabled="$loop->last" />
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="playlist_add" :colspan="5" :title="__('learning.empty.units')" compact />
                    @endforelse
                </x-table>
            </x-card>

            @if ($canEditContent && $course->sections->isNotEmpty())
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="segment" class="text-muted" /> {{ __('learning.field.sections') }}
                    </h3>
                    <ul class="space-y-1">
                        @foreach ($course->sections as $section)
                            <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 px-3 py-1.5 text-sm">
                                <span class="font-medium">{{ $section->position }}. {{ $section->title }}</span>
                                <div class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('learning.courses.sections.move', [$course, $section]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <x-icon-btn icon="arrow_upward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_up')" :disabled="$loop->first" />
                                    </form>
                                    <form method="POST" action="{{ route('learning.courses.sections.move', [$course, $section]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <x-icon-btn icon="arrow_downward" tone="ghost" size="xs" type="submit" :label="__('learning.action.move_down')" :disabled="$loop->last" />
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="history_edu" class="text-muted" /> {{ __('learning.field.versions') }}
                </h3>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('learning.field.version') }}</th>
                            <th>{{ __('learning.field.released_at') }}</th>
                            <th>{{ __('learning.field.training_course_version') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($course->versions as $version)
                        <tr class="hover">
                            <td class="font-mono text-sm">
                                v{{ $version->version }}
                                @if ($version->label)
                                    <span class="text-base-content/70">· {{ $version->label }}</span>
                                @endif
                                @if ($version->is_current)
                                    <x-status-badge tone="success" size="sm">{{ __('learning.field.current') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-sm">{{ $version->released_at?->orgTz()->translatedFormat('d.m.Y H:i') ?? '–' }}</td>
                            <td class="text-sm text-base-content/70">
                                {{ $version->training_course_version_id ? __('learning.field.mirrored') : '–' }}
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="history_edu" :colspan="3" :title="__('learning.empty.versions')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="info" class="text-muted" /> {{ __('learning.field.course') }}
                </h3>
                @php
                    $audienceLabels = array_map(static fn ($a) => $a->label(), $course->audienceList());
                @endphp
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.code')" :value="$course->code" />
                    <x-detail-grid.row :label="__('learning.field.status')" :value="$course->status->label()" />
                    <x-detail-grid.row :label="__('learning.field.kind')" :value="$course->kind->label()" />
                    @if ($course->isExam())
                        <x-detail-grid.row :label="__('learning.field.exam_target')" :value="$course->examTarget?->title ?? '–'" />
                    @endif
                    @if ($course->prerequisites->isNotEmpty())
                        <x-detail-grid.row :label="__('learning.field.prerequisites')" :value="$course->prerequisites->pluck('title')->implode(', ') . ' (' . __('learning.field.prerequisite_mode_' . $course->prerequisite_mode) . ')'" />
                    @endif
                    <x-detail-grid.row :label="__('learning.field.time_policy')" :value="$course->time_policy->label()" />
                    <x-detail-grid.row :label="__('learning.field.instruction_suitability')" :value="$course->instruction_suitability->label()" />
                    <x-detail-grid.row :label="__('learning.field.access_kind')" :value="$course->access_kind->label()" />
                    <x-detail-grid.row :label="__('learning.field.training_course')" :value="$course->trainingCourse?->title ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.audiences')" :value="$audienceLabels !== [] ? implode(', ', $audienceLabels) : '–'" />
                    <x-detail-grid.row :label="__('learning.field.owner')" :value="$course->owner?->name ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.validity_months')" :value="$course->validity_months ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.duration_minutes')" :value="$course->duration_minutes ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.category')" :value="$course->category?->name ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.availability')"
                                       :value="($course->available_from?->translatedFormat('d.m.Y') ?? '…') . ' – ' . ($course->available_until?->translatedFormat('d.m.Y') ?? '…') . ($course->max_enrollments !== null ? ' · ' . __('learning.field.max_enrollments') . ': ' . $course->activeEnrollmentsCount() . ' / ' . $course->max_enrollments : '')" />
                </x-detail-grid>
            </x-card>

            @if ($canManageParticipants)
                {{-- Trainer (MVP-786): mit Org-Schalter sehen Autoren und
                     Bewertende nur Kurse, an denen sie stehen. --}}
                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="badge" class="text-muted" /> {{ __('learning.field.trainers') }}
                    </h3>
                    @if ($scopingEnabled)
                        <p class="mb-2 text-xs text-muted">{{ __('learning.help.scoping_active') }}</p>
                    @endif
                    <ul class="mb-3 space-y-1 text-sm">
                        @forelse ($trainers as $trainer)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $trainer->name }} <span class="text-xs text-muted">· {{ __('learning.field.trainer_role_' . $trainer->pivot->role) }}</span></span>
                                <form method="POST" action="{{ route('learning.courses.trainers.destroy', [$course, $trainer]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="person_remove" tone="ghost" size="xs" type="submit" :label="__('learning.action.remove_trainer')" />
                                </form>
                            </li>
                        @empty
                            <li class="text-muted">{{ __('learning.empty.trainers') }}</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('learning.courses.trainers.store', $course) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-select-field name="user_id" :label="__('learning.field.trainer')" required class="w-48">
                            @foreach ($trainerOptions as $option)
                                <option value="{{ $option->sqid }}">{{ $option->name }}</option>
                            @endforeach
                        </x-select-field>
                        <x-select-field name="role" :label="__('learning.field.trainer_role')" required class="w-36">
                            <option value="trainer">{{ __('learning.field.trainer_role_trainer') }}</option>
                            <option value="grader">{{ __('learning.field.trainer_role_grader') }}</option>
                        </x-select-field>
                        <x-icon-btn icon="person_add" tone="outline" size="sm" type="submit" :label="__('learning.action.add_trainer')" />
                    </form>
                </x-card>

                <x-card>
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="group" class="text-muted" /> {{ __('learning.field.participants') }}
                    </h3>
                    @php
                        $participantsTotal = (int) $enrollmentCounts->sum();
                    @endphp
                    @if ($participantsTotal === 0)
                        <p class="mb-3 text-sm text-base-content/70">{{ __('learning.empty.participants') }}</p>
                    @else
                        <div class="mb-3 flex flex-wrap gap-1">
                            @foreach (\App\Enums\Learning\LearningEnrollmentStatus::cases() as $case)
                                @if (($enrollmentCounts[$case->value] ?? 0) > 0)
                                    <x-status-badge :tone="$case->tone()" size="sm">{{ $case->label() }}: {{ $enrollmentCounts[$case->value] }}</x-status-badge>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <x-icon-btn icon="group" tone="outline" size="sm"
                                :href="route('learning.courses.enrollments.index', $course)"
                                show-label>{{ __('learning.action.participants') }}</x-icon-btn>
                </x-card>
            @endif

            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="publish" class="text-muted" /> {{ __('learning.field.release') }}
                </h3>
                <p class="mb-3 text-sm text-base-content/70">{{ __('learning.help.release') }}</p>
                <div class="flex flex-wrap gap-2">
                    @if ($canEditContent && $course->status === \App\Enums\Learning\LearningCourseStatus::Draft)
                        <form method="POST" action="{{ route('learning.courses.submit-review', $course) }}">
                            @csrf
                            <x-icon-btn icon="rate_review" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.submit_review') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($canRelease)
                        <form method="POST" action="{{ route('learning.courses.release', $course) }}" class="flex items-end gap-2">
                            @csrf
                            <x-input-field name="label" :label="__('learning.field.version_label')" maxlength="60" class="w-40" />
                            <x-icon-btn icon="publish" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.release') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($canEditMeta && $course->status === \App\Enums\Learning\LearningCourseStatus::Released)
                        <form method="POST" action="{{ route('learning.courses.reopen', $course) }}">
                            @csrf
                            <x-icon-btn icon="lock_open" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.reopen') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($canEditMeta && $course->status !== \App\Enums\Learning\LearningCourseStatus::Archived)
                        <form method="POST" action="{{ route('learning.courses.archive', $course) }}">
                            @csrf
                            <x-icon-btn icon="archive" tone="ghost" size="sm" type="submit" show-label>{{ __('learning.action.archive') }}</x-icon-btn>
                        </form>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    {{-- Kursübersetzungen (MVP-748). Eine Lesehilfe an derselben
         Kursversion — maßgeblich bleibt die Ausgangssprache, und sichtbar
         wird eine Übersetzung erst nach Freigabe durch einen Menschen. --}}
    <x-card>
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <x-icon name="translate" class="text-muted" /> {{ __('learning.field.translations') }}
        </h3>

        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('learning.field.locale') }}</th>
                    <th>{{ __('learning.field.translation_status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($translations as $translation)
                @php $stale = $translation->source_hash !== $sourceHash; @endphp
                <tr>
                    <td class="font-mono text-xs">{{ $translation->locale }}</td>
                    <td>
                        @if ($stale)
                            {{-- Eine Übersetzung des vorletzten Textes wäre
                                 schlimmer als keine. --}}
                            <x-status-badge tone="warning" size="sm">{{ __('learning.field.outdated') }}</x-status-badge>
                        @elseif ($translation->status === \App\Enums\Learning\LearningTranslationStatus::Approved)
                            <x-status-badge tone="success" size="sm">{{ $translation->status->label() }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ $translation->status->label() }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if (! $stale && $translation->status !== \App\Enums\Learning\LearningTranslationStatus::Approved)
                            <form method="POST" action="{{ route('learning.courses.translations.approve', [$course->sqid, $translation->sqid]) }}"
                                  class="flex justify-end">
                                @csrf
                                <x-icon-btn icon="check_circle" tone="success" size="xs" type="submit"
                                            :label="__('learning.action.approve_translation')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="translate" :message="__('learning.empty.translations')" colspan="3" compact />
            @endforelse
        </x-table>

        <form method="POST" action="{{ route('learning.courses.translate', $course->sqid) }}" class="mt-3">
            @csrf
            <div class="flex flex-wrap items-end gap-2">
                <x-select-field name="locale" :label="__('learning.field.locale')" required class="grow">
                    @foreach ($locales as $locale)
                        <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                    @endforeach
                </x-select-field>
                <x-icon-btn icon="translate" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.translate') }}</x-icon-btn>
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('learning.help.translate') }}</p>
        </form>
    </x-card>

    {{-- Verweise und Rückverweise (MVP-811). --}}
    <x-content-references :subject="$course" />
</x-page-shell>
@endsection

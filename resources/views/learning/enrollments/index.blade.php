{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Teilnehmer eines Kurses (Feature 149, MVP-778): Einschreibungen mit Status,
  Quelle, Fortschritt und Fristen; manuell einschreiben, Frist/Zugang ändern,
  stornieren, Einstiegslink für Externe.
--}}
@extends('layouts.app')
@section('title', __('learning.title.participants'))
@section('nav-title', $course->title)
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="$course->title">
    <x-slot:actions>
        @if ($canEnroll)
            <x-icon-btn icon="person_add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('learning.courses.enrollments.create', $course)"
                        show-label>{{ __('learning.action.add_participant') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                    :href="route('learning.courses.show', $course)"
                    show-label>{{ __('learning.action.back') }}</x-icon-btn>
        <x-help-button topic="learning.overview" />
    </x-slot:actions>

    @unless ($canEnroll)
        <div class="alert alert-info text-sm" role="status">
            <x-icon name="info" />
            <span>{{ __('learning.help.participants_requires_release') }}</span>
        </div>
    @endunless

    <x-filter-bar :action="route('learning.courses.enrollments.index', $course)" :reset="route('learning.courses.enrollments.index', $course)">
        <x-filter-field :label="__('learning.field.search')" for="flt-participant-q" class="flex-1 min-w-60">
            <input id="flt-participant-q" type="search" name="q" value="{{ $search }}"
                   placeholder="{{ __('learning.field.learner') }}"
                   class="input input-sm input-bordered w-full">
        </x-filter-field>
        <x-filter-field :label="__('learning.field.status')" for="flt-participant-status">
            <select id="flt-participant-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_status') }}</option>
                @foreach (\App\Enums\Learning\LearningEnrollmentStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }} ({{ $counts[$case->value] ?? 0 }})</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.source')" for="flt-participant-source">
            <select id="flt-participant-source" name="source" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_sources') }}</option>
                @foreach (\App\Enums\Learning\LearningEnrollmentSource::cases() as $case)
                    <option value="{{ $case->value }}" @selected($source === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.learner') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.status') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.source') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.progress') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.due_at') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.access_until') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.enrolled_at') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($enrollments as $enrollment)
            @php
                $isExternal = $enrollment->external_participant_id !== null;
                $isOpen = ! $enrollment->status->isFinal();
            @endphp
            <tr class="hover">
                <td class="font-medium">
                    {{ $enrollment->learnerName() }}
                    @if ($isExternal)
                        <x-status-badge tone="ghost" size="sm" outline>{{ __('learning.field.external_badge') }}</x-status-badge>
                    @endif
                    @if ($enrollment->courseVersion)
                        <span class="text-xs text-muted">· v{{ $enrollment->courseVersion->version }}</span>
                    @endif
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$enrollment->status->tone()" size="sm">{{ $enrollment->status->label() }}</x-status-badge>
                    @if ($isOpen && $enrollment->isAccessExpired())
                        <x-status-badge tone="warning" size="sm" outline>{{ __('learning.field.access_expired') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$enrollment->source->tone()" size="sm" outline>{{ $enrollment->source->label() }}</x-status-badge>
                </td>
                <td class="text-center text-sm" data-sort-value="{{ (int) $enrollment->completed_units_count }}">
                    {{ (int) $enrollment->completed_units_count }} / {{ $mandatoryUnits }}
                </td>
                <td class="text-sm">{{ $enrollment->due_at?->translatedFormat('d.m.Y') ?? '–' }}</td>
                <td class="text-sm">{{ $enrollment->access_until?->translatedFormat('d.m.Y') ?? '–' }}</td>
                <td class="text-sm">{{ $enrollment->created_at?->translatedFormat('d.m.Y') }}</td>
                <td class="text-right whitespace-nowrap">
                    <div class="flex justify-end gap-1">
                        @if ($isOpen)
                            <x-icon-btn icon="event_repeat" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('learning.courses.enrollments.edit', [$course, $enrollment])"
                                        :label="__('learning.action.extend_access')" />
                            @if ($hasQuizzes)
                                <x-icon-btn icon="replay" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('learning.courses.enrollments.waiver', [$course, $enrollment])"
                                            :label="__('learning.action.grant_waiver')" />
                            @endif
                            @if ($isExternal)
                                <form method="POST" action="{{ route('learning.courses.enrollments.access-link', [$course, $enrollment]) }}"
                                      data-confirm-dialog data-confirm-message="{{ __('learning.confirm.send_access_link') }}">
                                    @csrf
                                    <x-icon-btn icon="link" tone="outline" size="xs" type="submit" :label="__('learning.action.send_access_link')" />
                                </form>
                            @endif
                            @if ($enrollment->source !== \App\Enums\Learning\LearningEnrollmentSource::Requirement)
                                <form method="POST" action="{{ route('learning.courses.enrollments.cancel', [$course, $enrollment]) }}" class="flex items-center gap-1">
                                    @csrf
                                    {{-- Ein Platzhalter ist keine Beschriftung (WCAG 3.3.2). --}}
                                    <label class="sr-only" for="cancel-reason-{{ $enrollment->sqid }}">{{ __('learning.field.reason') }}</label>
                                    <input type="text" id="cancel-reason-{{ $enrollment->sqid }}" name="reason"
                                           class="input input-xs input-bordered w-32"
                                           placeholder="{{ __('learning.field.reason') }}" minlength="2" maxlength="255" required>
                                    <x-icon-btn icon="person_remove" tone="ghost" size="xs" type="submit" :label="__('learning.action.cancel_enrollment')" />
                                </form>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="group" :colspan="8" :title="__('learning.empty.participants')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$enrollments" standing />
</x-index-page>
@endsection

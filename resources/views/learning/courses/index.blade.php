{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lernkatalog (Feature 149): Kurse mit Status, Zielgruppen und Zeitpolitik.
  Das Soll („wer muss was bis wann") bleibt im Schulungskatalog (145).
--}}
@extends('layouts.app')
@section('title', __('learning.title.courses'))
@section('nav-title', __('learning.title.courses'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.courses')">
    <x-slot:actions>
        <x-help-button topic="learning.overview" />
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('learning.courses.create')"
                        show-label>{{ __('learning.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('learning.courses.index')" :reset="route('learning.courses.index')">
        <x-filter-field :label="__('learning.kpi.released')" for="flt-released-count">
            <span id="flt-released-count" class="badge badge-ghost badge-sm">{{ $releasedCount }}</span>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_status') }}</option>
                @foreach (\App\Enums\Learning\LearningCourseStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.title') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.status') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.time_policy') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.training_course') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.units_count') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.duration_minutes') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($courses as $course)
            <tr class="hover">
                <td class="font-medium">
                    <a class="link link-hover" href="{{ route('learning.courses.show', $course) }}">{{ $course->title }}</a>
                    @if ($course->certificate_enabled)
                        <x-status-badge tone="info" size="sm" outline>{{ __('learning.field.certificate') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$course->status->tone()" size="sm" outline>{{ $course->status->label() }}</x-status-badge>
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$course->time_policy->tone()" size="sm">{{ $course->time_policy->label() }}</x-status-badge>
                </td>
                <td class="text-sm text-base-content/70">{{ $course->trainingCourse?->title ?? '–' }}</td>
                <td class="text-center text-sm">{{ $course->units_count }}</td>
                <td class="text-center text-sm">{{ $course->duration_minutes ?? '–' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('learning.courses.show', $course)" :label="__('learning.action.show')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="school" :colspan="7" :title="__('learning.empty.courses')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$courses" standing />
</x-index-page>
@endsection

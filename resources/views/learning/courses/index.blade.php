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
        @can(\App\Enums\User\Permission::LearningManage->value)
            <x-icon-btn icon="settings" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('learning.settings.edit')"
                        show-label>{{ __('learning.title.settings') }}</x-icon-btn>
            <x-icon-btn icon="hub" tone="ghost" size="sm"
                        :href="route('learning.lti-registrations.index')"
                        show-label>{{ __('learning.lti_registration.title') }}</x-icon-btn>
        @endcan
        {{-- Liste/Kacheln (MVP-794): der Umschalter merkt sich die Wahl je Person. --}}
        <x-icon-btn :icon="$viewMode === 'tiles' ? 'view_list' : 'grid_view'" tone="ghost" size="sm"
                    :href="route('learning.courses.index', array_merge(request()->query(), ['view' => $viewMode === 'tiles' ? 'list' : 'tiles']))"
                    show-label>{{ $viewMode === 'tiles' ? __('learning.action.view_list') : __('learning.action.view_tiles') }}</x-icon-btn>
        @if ($canCreate)
            <x-icon-btn icon="upload_file" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('learning.courses.import-learndash.create')"
                        show-label>{{ __('learning.action.import_learndash') }}</x-icon-btn>
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
        <x-filter-field :label="__('learning.field.kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_course_kinds') }}</option>
                @foreach (\App\Enums\Learning\LearningCourseKind::cases() as $case)
                    <option value="{{ $case->value }}" @selected($kind === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        @if ($categories->isNotEmpty())
            <x-filter-field :label="__('learning.field.category')" for="flt-category">
                <select id="flt-category" name="category" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('learning.filter.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->sqid }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('learning.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_status') }}</option>
                @foreach (\App\Enums\Learning\LearningCourseStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($viewMode === 'tiles')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($courses as $course)
                <x-card>
                    <div class="flex items-start justify-between gap-2">
                        <a class="link link-hover font-medium" href="{{ route('learning.courses.show', $course) }}">{{ $course->title }}</a>
                        <x-status-badge :tone="$course->status->tone()" size="sm" outline>{{ $course->status->label() }}</x-status-badge>
                    </div>
                    @if ($course->subtitle)
                        <p class="mt-1 text-sm text-muted">{{ $course->subtitle }}</p>
                    @endif
                    <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                        <span>{{ $course->units_count }} {{ __('learning.field.units') }}</span>
                        @if ($course->duration_minutes) <span>· {{ $course->duration_minutes }} {{ __('learning.field.minutes_short') }}</span> @endif
                        @if ($course->category) <span>· {{ $course->category->name }}</span> @endif
                        @if (isset($ratings[$course->id]))
                            <span title="{{ __('learning.help.rating', ['count' => $ratings[$course->id]['count']]) }}">· ★ {{ number_format($ratings[$course->id]['average'], 1, ',', '') }}</span>
                        @endif
                    </p>
                </x-card>
            @empty
                <x-empty-state icon="school" :title="__('learning.empty.courses')" class="sm:col-span-2 xl:col-span-3" />
            @endforelse
        </div>
        <x-pagination :paginator="$courses" standing />
    @else
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
                    @if ($course->isExam())
                        <x-status-badge :tone="$course->kind->tone()" size="sm">{{ $course->kind->label() }}</x-status-badge>
                    @endif
                    @if (isset($ratings[$course->id]))
                        <span class="ml-1 text-xs text-muted" title="{{ __('learning.help.rating', ['count' => $ratings[$course->id]['count']]) }}">★ {{ number_format($ratings[$course->id]['average'], 1, ',', '') }}</span>
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
    @endif
</x-index-page>
@endsection

{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Meine Schulungen" (Feature 149): eigene Kurse mit Frist und Fortschritt.
  Bewusst ohne Plan-Gate — eine Pflichtunterweisung darf nie an der
  Lizenzstufe scheitern.
--}}
@extends('layouts.app')
@section('title', __('learning.title.my'))
@section('nav-title', __('learning.title.my'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.my')">
    <x-slot:actions>
        <x-help-button topic="learning.overview" />
    </x-slot:actions>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.title') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.status') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.due_at') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.progress') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($enrollments as $enrollment)
            @php
                $total = $enrollment->course?->units()->count() ?? 0;
                $done = $enrollment->progress->where('status', \App\Enums\Learning\LearningProgressStatus::Completed)->count();
            @endphp
            <tr class="hover">
                <td class="font-medium">
                    <a class="link link-hover" href="{{ route('learning.my.show', $enrollment) }}">{{ $enrollment->course?->title }}</a>
                    @if ($enrollment->course?->time_policy === \App\Enums\Learning\LearningTimePolicy::WorkTimeRequired)
                        <x-status-badge tone="warning" size="sm" outline>{{ __('learning.badge.work_time_only') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$enrollment->status->tone()" size="sm">{{ $enrollment->status->label() }}</x-status-badge>
                </td>
                <td class="text-sm">{{ $enrollment->due_at?->translatedFormat('d.m.Y') ?? '–' }}</td>
                <td class="text-center text-sm">{{ $done }} / {{ $total }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="play_arrow" tone="primary" size="xs"
                                    :href="route('learning.my.show', $enrollment)"
                                    :label="__('learning.action.open_course')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="school" :colspan="5" :title="__('learning.empty.my')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection

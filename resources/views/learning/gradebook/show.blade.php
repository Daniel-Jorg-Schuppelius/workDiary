{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Notenbuch je Kurs (Feature 149, MVP-790): Lernende × Komponenten.
  Variablen: $course, $components, $rows.
--}}
@extends('layouts.app')
@section('title', __('learning.title.gradebook'))
@section('nav-title', $course->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->title" :badge="__('learning.title.gradebook')">
            <x-slot:actions>
                <x-icon-btn icon="tune" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('learning.courses.gradebook.components.edit', $course)"
                            show-label>{{ __('learning.action.edit_components') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('learning.courses.gradebook.csv', $course)"
                            show-label>{{ __('learning.action.export_csv') }}</x-icon-btn>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.show', $course)"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($components->isEmpty())
        <div class="alert alert-info mb-4 text-sm" role="status">
            <x-icon name="info" />
            <span>{{ __('learning.empty.components') }}</span>
        </div>
    @endif

    <x-card>
        <x-table :bare="true" size="sm">
            <x-slot:head>
                <tr>
                    <th>{{ __('learning.field.learner') }}</th>
                    <th>{{ __('learning.field.status') }}</th>
                    @foreach ($components as $part)
                        <th class="text-center">
                            {{ $part->title }}
                            @if ($part->weight_percent !== null)
                                <span class="block text-xs font-normal text-muted">{{ $part->weight_percent }} %</span>
                            @endif
                        </th>
                    @endforeach
                    <th class="text-right">{{ __('learning.field.total') }}</th>
                    <th class="text-center">{{ __('learning.field.grade') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                @php
                    $enrollment = $row['enrollment'];
                    $result = $row['result'];
                    $cells = collect($result['components'])->keyBy('component_id');
                @endphp
                <tr class="hover">
                    <td class="font-medium">{{ $enrollment->learnerName() }}</td>
                    <td class="text-sm"><x-status-badge :tone="$enrollment->status->tone()" size="sm">{{ $enrollment->status->label() }}</x-status-badge></td>
                    @foreach ($components as $part)
                        @php $cell = $cells->get($part->id); @endphp
                        <td class="text-center text-sm">
                            @if ($cell === null || $cell['pending'])
                                <span class="text-muted">–</span>
                            @else
                                {{ $cell['points'] }} / {{ $cell['max'] }}
                                <span class="block text-xs text-muted">{{ $cell['percent'] }} %</span>
                            @endif
                            @if ($part->isManual())
                                <x-icon-btn icon="edit_note" tone="ghost" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('learning.courses.gradebook.grade.create', [$course, $enrollment, $part])"
                                            :label="__('learning.action.record_grade')" />
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right text-sm">
                        @if ($result['pending'])
                            <x-status-badge tone="info" size="sm">{{ __('learning.field.pending_grading') }}</x-status-badge>
                        @else
                            {{ $result['percent'] }} %
                        @endif
                    </td>
                    <td class="text-center text-sm">{{ $result['grade'] ?? '–' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="picture_as_pdf" tone="ghost" size="xs"
                                        :href="route('learning.courses.gradebook.report-card', [$course, $enrollment])"
                                        :label="__('learning.action.report_card')" />
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="grading" :colspan="5 + $components->count()" :title="__('learning.empty.gradebook')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection

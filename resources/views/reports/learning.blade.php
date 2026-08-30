{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kursanalyse (Feature 149, MVP-747): Quoten und Auffälligkeiten. Kleine
  Gruppen werden nicht ausgewiesen — sonst ließe sich von der Quote auf
  einzelne Personen zurückrechnen.
--}}
@extends('layouts.app')
@section('title', __('learning.title.report'))
@section('nav-title', __('learning.title.report'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.subtitle.report')">
            <x-slot:actions>
                {{-- Unterdrückte Quoten bleiben auch im Export unterdrückt. --}}
                <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm"
                            :href="route('reports.learning', ['export' => 'pdf'])"
                            show-label>{{ __('learning.action.export_pdf') }}</x-icon-btn>
                <x-icon-btn icon="table_view" tone="ghost" size="sm"
                            :href="route('reports.learning', ['export' => 'csv'])"
                            show-label>{{ __('learning.action.export_csv') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-4">
        <x-kpi-tile :label="__('learning.field.courses')" :value="$summary['courses']" />
        <x-kpi-tile :label="__('learning.field.enrollments')" :value="$summary['enrollments']" />
        <x-kpi-tile :label="__('learning.field.completed')" :value="$summary['completed']" tone="success" />
        <x-kpi-tile :label="__('learning.field.attempts')" :value="$summary['attempts']" />
    </div>

    <x-card class="mt-4">
        <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.completion_rate') }}</h3>
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('learning.field.course') }}</th>
                    <th class="text-center">{{ __('learning.field.enrollments') }}</th>
                    <th class="text-center">{{ __('learning.field.completed') }}</th>
                    <th class="text-center">{{ __('learning.field.completion_rate') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($completion as $row)
                <tr class="hover">
                    <td class="font-medium">{{ $row['course']->title }}</td>
                    <td class="text-center text-sm">{{ $row['enrolled'] }}</td>
                    <td class="text-center text-sm">{{ $row['completed'] }}</td>
                    <td class="text-center text-sm">
                        @if ($row['rate'] !== null)
                            {{ $row['rate'] }} %
                        @else
                            <span class="text-muted" title="{{ __('learning.help.min_group', ['count' => $minGroup]) }}">–</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="school" :colspan="4" :title="__('learning.empty.report')" compact />
            @endforelse
        </x-table>
        <p class="mt-2 text-xs text-muted">{{ __('learning.help.min_group', ['count' => $minGroup]) }}</p>
    </x-card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.drop_offs') }}</h3>
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('learning.field.unit') }}</th>
                        <th class="text-center">{{ __('learning.field.drop') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($dropOffs as $row)
                    <tr class="hover">
                        <td>
                            <span class="font-medium">{{ $row['unit_title'] }}</span>
                            <span class="text-xs text-muted">· {{ $row['course_title'] }}</span>
                        </td>
                        <td class="text-center text-sm">{{ $row['drop'] }} / {{ $row['started'] }}</td>
                    </tr>
                @empty
                    <x-table.empty icon="trending_down" :colspan="2" :title="__('learning.empty.drop_offs')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.hard_questions') }}</h3>
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('learning.field.question') }}</th>
                        <th class="text-center">{{ __('learning.field.error_rate') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($questions as $row)
                    <tr class="hover">
                        <td class="text-sm">{{ $row['prompt'] }}</td>
                        <td class="text-center text-sm">{{ $row['error_rate'] }} %</td>
                    </tr>
                @empty
                    <x-table.empty icon="help" :colspan="2" :title="__('learning.empty.hard_questions')" compact />
                @endforelse
            </x-table>
            <p class="mt-2 text-xs text-muted">{{ __('learning.help.hard_questions') }}</p>
        </x-card>
    </div>
</x-page-shell>
@endsection

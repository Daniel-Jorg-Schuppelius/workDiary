{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : training.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schulungs-Auswertung (Feature 145): Erfüllungsgrad je Team, Rolle und
  Kurs zum Stichtag, Export CSV/XLSX/PDF (Framework Feature 002).
--}}
@extends('layouts.app')
@section('title', __('training.report.title'))
@section('nav-title', __('training.report.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('training.report.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.training', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.training', array_merge($standardFilters->toQueryParams(), ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.training', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.training')" :reset="route('reports.training')">
        @include('reports._standard_filters', ['idPrefix' => 'training'])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar-h :title="__('training.report.rate_by_team')" unit="%"
                        :series="$teamSeries" :x-label="__('training.report.team')" y-label="%" />
        <x-charts.bar-h :title="__('training.report.rate_by_course')" unit="%"
                        :series="$courseSeries" :x-label="__('training.report.course')" y-label="%" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('training.report.kpi.assignments')" :value="$report['totals']['assignments']" />
        <x-kpi-tile :label="__('training.report.kpi.fulfilled')" :value="$report['totals']['fulfilled']" tone="success" />
        <x-kpi-tile :label="__('training.report.kpi.due')" :value="$report['totals']['due']" :tone="$report['totals']['due'] > 0 ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('training.report.kpi.overdue')" :value="$report['totals']['overdue']" :tone="$report['totals']['overdue'] > 0 ? 'error' : 'neutral'" />
        <x-kpi-tile :label="__('training.report.kpi.rate')" :value="$report['totals']['rate'] . ' %'" />
    </div>

    @if ($report['totals']['assignments'] === 0)
        <x-card>
            <x-empty-state icon="school" :title="__('training.report.empty')" />
        </x-card>
    @else
        @foreach ([['by_team', 'byTeam'], ['by_role', 'byRole'], ['by_course', 'byCourse']] as [$labelKey, $dataKey])
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('training.report.' . $labelKey) }}</h3>
                <x-table bare table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string" default>{{ __('training.report.' . str_replace('by_', '', $labelKey)) }}</x-table.th>
                            <x-table.th sort type="number" align="center">{{ __('training.report.kpi.assignments') }}</x-table.th>
                            <x-table.th sort type="number" align="center">{{ __('training.report.kpi.fulfilled') }}</x-table.th>
                            <x-table.th sort type="number" align="center">{{ __('training.report.kpi.due') }}</x-table.th>
                            <x-table.th sort type="number" align="center">{{ __('training.report.kpi.overdue') }}</x-table.th>
                            <x-table.th sort type="number" align="center">{{ __('training.report.rate') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($report[$dataKey] as $group)
                        <tr class="hover">
                            <td class="font-medium">{{ $group['label'] }}</td>
                            <td class="text-center text-sm">{{ $group['total'] }}</td>
                            <td class="text-center text-sm">{{ $group['fulfilled'] }}</td>
                            <td class="text-center text-sm">{{ $group['due'] }}</td>
                            <td class="text-center text-sm {{ $group['overdue'] > 0 ? 'font-semibold text-error' : '' }}">{{ $group['overdue'] }}</td>
                            <td class="text-center text-sm">{{ $group['rate'] }} %</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('training.report.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>
        @endforeach
    @endif
</x-page-shell>
@endsection

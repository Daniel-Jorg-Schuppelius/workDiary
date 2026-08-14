{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : allocations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('reporting.allocations.title'))
@section('nav-title', __('reporting.allocations.title'))

@section('content')
@php
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('reporting.allocations.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.allocations', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.allocations', ['export' => 'xlsx'])"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.allocations', ['export' => 'pdf'])"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('reporting.allocations.total')" :value="$fmtMin($totalMinutes)" />
        <x-kpi-tile :label="__('reporting.allocations.dimensions')" :value="count($groups)" />
    </div>

    @if ($groups === [])
        <x-card>
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">call_split</span>'
                           :title="__('reporting.allocations.empty')" />
        </x-card>
    @else
        @foreach ($groups as $group)
            <x-card :title="$group['label']">
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('reporting.allocations.col_target') }}</x-table.th>
                            <x-table.th sort type="duration" align="right">{{ __('reporting.allocations.col_minutes') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('reporting.allocations.col_entries') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['name'] }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $row['minutes'] }}">{{ $fmtMin($row['minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $row['entries'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endforeach
        <p class="text-xs text-base-content/50">{{ __('reporting.allocations.note') }}</p>
    @endif
</x-page-shell>
@endsection

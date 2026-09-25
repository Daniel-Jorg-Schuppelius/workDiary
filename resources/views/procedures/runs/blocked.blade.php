{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : blocked.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Blockierte Prozedurläufe (MVP-897): aktuell gesperrt und beendete Sperren im Zeitraum. --}}
@extends('layouts.app')

@section('title', __('procedure.blocked_report.title'))
@section('nav-title', __('procedure.blocked_report.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">{{ __('procedure.blocked_report.subtitle') }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="route('reports.procedure-blocked', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" :href="route('reports.procedure-blocked', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'xlsx'])" show-label>Excel</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.procedure-blocked')" :reset="route('reports.procedure-blocked')">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      :from="$from->toDateString()" :to="$to->toDateString()" />
    </x-filter-bar>

    <x-card :title="__('procedure.blocked_report.current')" :count="count($current)">
        @if ($current === [])
            <p class="text-sm text-muted">{{ __('procedure.blocked_report.none_current') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('procedure.blocked_report.template') }}</th><th>{{ __('procedure.blocked_report.reason') }}</th><th>{{ __('procedure.blocked_report.since') }}</th><th class="text-right">{{ __('procedure.blocked_report.hours') }}</th><th></th></tr>
                </x-slot:head>
                @foreach ($current as $row)
                    <tr>
                        <td>{{ $row['template'] }}</td>
                        <td>{{ __('procedure.blocked.' . $row['reason']) }}</td>
                        <td>{{ \App\Support\CarbonFmt::fdatetime($row['since']) }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['hours'], 1) }}</td>
                        <td class="text-right"><x-icon-btn icon="open_in_new" size="xs" :href="route('procedure-runs.show', $row['run'])" :label="__('procedure.report.open_run')" /></td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card :title="__('procedure.blocked_report.periods')" :count="count($periods)">
        @if ($periods === [])
            <p class="text-sm text-muted">{{ __('procedure.blocked_report.none_periods') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('procedure.blocked_report.reason') }}</th><th>{{ __('procedure.blocked_report.template') }}</th><th class="text-right">{{ __('procedure.blocked_report.count') }}</th><th class="text-right">{{ __('procedure.blocked_report.avg_hours') }}</th><th class="text-right">{{ __('procedure.blocked_report.max_hours') }}</th></tr>
                </x-slot:head>
                @foreach ($periods as $row)
                    <tr>
                        <td>{{ __('procedure.blocked.' . $row['reason']) }}</td>
                        <td>{{ $row['template'] }}</td>
                        <td class="text-right font-mono">{{ $row['count'] }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avg_hours'], 1) }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['max_hours'], 1) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rundgangsbericht je Route, Objekt und Zeitraum (MVP-1292). --}}
@extends('layouts.app')
@section('title', __('patrol.report.title'))
@section('nav-title', __('patrol.report.title'))

@section('content')
<x-index-page :subtitle="__('patrol.report.subtitle')">
    <x-slot:actions>
        <x-report-export :url="fn (string $format) => route('patrols.report', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'route' => $filters['route'] ?: null, 'site' => $filters['site'] ?: null, 'export' => $format]))" tone="ghost" />
    </x-slot:actions>

    <x-filter-bar :action="route('patrols.report')" :reset="route('patrols.report')">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      :from="$from->toDateString()" :to="$to->toDateString()"
                      :from-label="__('von')" :to-label="__('bis')" />
        <select name="route" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Route') }}">
            <option value="">{{ __('patrol.report.allRoutes') }}</option>
            @foreach ($routes as $route)
                <option value="{{ $route->sqid }}" @selected($filters['route'] === $route->sqid)>{{ $route->name }}</option>
            @endforeach
        </select>
        @if ($sites->isNotEmpty())
            <select name="site" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Objekt') }}">
                <option value="">{{ __('patrol.report.allSites') }}</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->sqid }}" @selected($filters['site'] === $site->sqid)>{{ $site->name }}</option>
                @endforeach
            </select>
        @endif
    </x-filter-bar>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-card><div class="text-xs text-muted">{{ __('patrol.report.runs') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['runs'] }}</div></x-card>
        <x-card><div class="text-xs text-muted">{{ __('patrol.report.punctuality') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['punctuality'] !== null ? $totals['punctuality'] . ' %' : '—' }}</div></x-card>
        <x-card><div class="text-xs text-muted">{{ __('patrol.report.missed') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['missed'] }}</div></x-card>
        <x-card><div class="text-xs text-muted">{{ __('patrol.report.aborted') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['aborted'] }}</div></x-card>
    </div>

    <x-table>
        <x-slot:head>
            <th>{{ __('Beginn') }}</th>
            <th>{{ __('Route') }}</th>
            <th>{{ __('Objekt') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-right">{{ __('patrol.report.expected') }}</th>
            <th class="text-right">{{ __('patrol.report.onTime') }}</th>
            <th class="text-right">{{ __('patrol.report.late') }}</th>
            <th class="text-right">{{ __('patrol.report.missed') }}</th>
            <th>{{ __('patrol.report.note') }}</th>
        </x-slot:head>
        @forelse ($rows as $row)
            @php($run = $row['run'])
            <tr>
                <td class="whitespace-nowrap"><a class="link" href="{{ route('patrols.runs.show', $run) }}">{{ $run->started_at?->fdatetime() }}</a></td>
                <td>{{ $run->route?->name }}</td>
                <td>{{ $run->route?->site?->name ?? '—' }}</td>
                <td><x-status-badge size="sm">{{ $run->status->label() }}</x-status-badge></td>
                <td class="text-right tabular-nums">{{ $row['expected'] }}</td>
                <td class="text-right tabular-nums">{{ $row['on_time'] }}</td>
                <td class="text-right tabular-nums">{{ $row['late'] }}</td>
                <td class="text-right tabular-nums">{{ $row['missed'] }}</td>
                <td class="text-sm">{{ $run->deviation_note ?? '' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="9" compact :title="__('patrol.report.empty')" />
        @endforelse
    </x-table>
</x-index-page>
@endsection

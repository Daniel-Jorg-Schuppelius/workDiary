{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : qualifications.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('nav-title', __('Qualifikationsmatrix'))

@section('content')
@php
    $cellClass = fn (?array $c) => match ($c['state'] ?? null) {
        'expired'  => 'bg-error/20 text-error font-bold',
        'expiring' => 'bg-warning/20 text-warning-content font-semibold',
        'valid'    => 'bg-success/15 text-success-content',
        default    => 'bg-base-200 text-muted',
    };
    $cellText = function (?array $c): string {
        if ($c === null) {
            return '–';
        }
        if ($c['valid_until'] === null) {
            return '✓';
        }
        return \Carbon\Carbon::parse($c['valid_until'])->fdate();
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Qualifikationsmatrix der Mitarbeiter inkl. Ablauf- und Warnstatus.')">
            <x-slot:actions>
                <x-report-export :url="fn (string $format) => route('reports.qualifications', array_merge($standardFilters->toQueryParams(), ['export' => $format]))" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($seesTeam)
        <x-filter-bar :action="route('reports.qualifications')" :reset="route('reports.qualifications')">
            @include('reports._standard_filters', ['idPrefix' => 'qualifications'])
        </x-filter-bar>
    @endif

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar-h :title="__('Träger je Qualifikation (Top 15)')" :unit="__('Personen')"
                        :series="$holdersSeries" :x-label="__('Qualifikation')" :y-label="__('Personen')" />
        <x-charts.stacked-bar :title="__('Zuweisungen je Qualifikation nach Status')" :unit="__('Zuweisungen')"
                              :series="$stateSeries" :bands="$stateBands" :x-label="__('Qualifikation')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('user.employees')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Qualifikationen')" :value="$totals['qualifications']" />
        <x-kpi-tile :label="__('Zuweisungen')" :value="$totals['assignments']" />
        <x-kpi-tile :label="__('Laufen ab (≤30 T.)')" :value="$totals['expiring']" :tone="$totals['expiring'] > 0 ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('Abgelaufen')" :value="$totals['expired']" :tone="$totals['expired'] > 0 ? 'error' : 'neutral'" />
    </div>

    <x-card>
        @if ($users->isEmpty() || $qualifications->isEmpty())
            <x-empty-state icon="workspace_premium" :title="__('Keine Qualifikations-Zuweisungen vorhanden.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" class="sticky left-0 bg-base-100">{{ __('Mitarbeiter') }}</x-table.th>
                        @foreach ($qualifications as $q)
                            <th class="text-center align-bottom" title="{{ $q->name }}">
                                <span class="block text-xs font-semibold">{{ $q->abbreviation ?? $q->name }}</span>
                            </th>
                        @endforeach
                    </tr>
                </x-slot:head>
                @foreach ($users as $u)
                    <tr>
                        <td class="sticky left-0 bg-base-100 font-semibold">{{ $u->name }}</td>
                        @foreach ($qualifications as $q)
                            @php $cell = $matrix[(int) $u->id][(int) $q->id] ?? null; @endphp
                            <td class="text-center text-xs tabular-nums {{ $cellClass($cell) }}">{{ $cellText($cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-3 flex flex-wrap gap-3 text-xs text-base-content/70">
                <x-status-badge tone="plain" size="md" class="bg-success/15">{{ __('gültig') }}</x-status-badge>
                <x-status-badge tone="plain" size="md" class="bg-warning/20">{{ __('läuft in 30 Tagen ab') }}</x-status-badge>
                <x-status-badge tone="plain" size="md" class="bg-error/20">{{ __('abgelaufen') }}</x-status-badge>
                <x-status-badge tone="plain" size="md" class="bg-base-200">{{ __('keine Zuweisung') }}</x-status-badge>
            </div>
        @endif
    </x-card>
</x-page-shell>
@endsection

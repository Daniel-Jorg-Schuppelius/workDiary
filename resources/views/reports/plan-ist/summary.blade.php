{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : summary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist-Anwesenheit Team-/Org-Sicht (MVP-018, Rang 38): Summen je
  Mitarbeiter:in mit Drilldown auf die Personen-Sicht.
--}}

@extends('layouts.app')
@section('title', $scope === 'team' ? __('Plan/Ist — Team') : __('Plan/Ist — Organisation'))
@section('nav-title', $scope === 'team' ? __('Plan/Ist — Team') : __('Plan/Ist — Organisation'))

@section('content')
@php
    $fmtH = fn (int $minutes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes / 60, 1, withThousandsSeparator: true) . ' h';
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $scope === 'team' ? __('Plan/Ist — Team') : __('Plan/Ist — Organisation') }}</x-slot:title>
            <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Dimension-Umschalter (A14 · MVP-333): Anwesenheit | Schicht | Projekt | Standort. --}}
    @include('reports.plan-ist._dimensions')

    {{-- Gemeinsame Filterleiste statt freiem GET-Formular (Vollaudit 2026-07, N58). --}}
    <x-filter-bar :action="url()->current()" :reset="url()->current()">
        @if ($scope === 'team' && $teams->count() > 1)
            <x-filter-field :label="__('Team')" for="plan-ist-team">
                <select id="plan-ist-team" name="team" class="select select-sm select-bordered shrink-0">
                    @foreach ($teams as $t)
                        <option value="{{ $t->sqid }}" @selected($team !== null && (int) $t->id === (int) $team->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-date-range class="w-80 shrink-0" :label="false"
                      from-name="from" to-name="to"
                      :from="$from->toDateString()" :to="$to->toDateString()"
                      :from-label="__('Von')" :to-label="__('Bis')" />
    </x-filter-bar>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Plan')" :value="$fmtH($summary['totals']['plan_minutes'])" tone="info" />
        <x-kpi-tile :label="__('Ist')" :value="$fmtH($summary['totals']['actual_minutes'])" tone="primary" />
        <x-kpi-tile :label="__('Differenz')" :value="$fmtH($summary['totals']['delta_minutes'])" :tone="$summary['totals']['delta_minutes'] < 0 ? 'warning' : 'success'" />
        <x-kpi-tile :label="__('Warnungen')" :value="$summary['totals']['warnings']" :tone="$summary['totals']['warnings'] > 0 ? 'error' : 'neutral'" format="int" />
    </div>

    <x-card :title="$scope === 'team' ? ($team?->name ?? '') : __('Alle Mitarbeitenden')" icon="groups">
        @if ($summary['rows'] === [])
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' :title="__('Keine Mitarbeitenden im gewählten Bereich.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Mitarbeiter:in') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ist (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Differenz (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Warnungen') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($summary['rows'] as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['plan_minutes'] }}">{{ $fmtH($row['plan_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['actual_minutes'] }}">{{ $fmtH($row['actual_minutes']) }}</td>
                        <td class="text-right tabular-nums {{ $row['delta_minutes'] < 0 ? 'text-warning' : '' }}" data-sort-value="{{ $row['delta_minutes'] }}">{{ $fmtH($row['delta_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['warnings'] }}">
                            @if ($row['warnings'] > 0)
                                <x-status-badge size="xs" tone="error">{{ $row['warnings'] }}</x-status-badge>
                            @else
                                0
                            @endif
                        </td>
                        <td class="text-right">
                            {{-- Drilldown auf die Personen-Sicht (org-gescopt + rechte-geprüft im Controller). --}}
                            <x-icon-btn icon="zoom_in" tone="ghost" size="xs"
                                        :href="route('reports.plan-ist.presence', ['user' => $row['user']->sqid, 'from' => $from->toDateString(), 'to' => $to->toDateString()])"
                                        :label="__('Details')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

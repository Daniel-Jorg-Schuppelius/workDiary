{{--
  Created on   : Tue Nov 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : project-persons.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Plan/Ist Projekt je Person (Feature 194, MVP-1469): Soll aus den
  Projekt-Sollzeiten, Ist aus der gebuchten Personenzeit, Tage unter Soll aus
  den Befunden; Aufenthalt im Geofence des Projekts nur als Beleg.
--}}

@extends('layouts.app')
@section('title', __('Plan/Ist — Projekt je Person'))
@section('nav-title', __('Plan/Ist — Projekt je Person'))

@section('content')
@php
    $fmtH = fn (int $minutes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes / 60, 1, withThousandsSeparator: true) . ' h';
    $hasVisits = collect($allRows)->contains(fn (array $r): bool => $r['visit_minutes'] !== null);
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.plan-ist._dimensions')

    <x-filter-bar :action="route('reports.plan-ist.project-persons')" :reset="route('reports.plan-ist.project-persons')">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      :from="$from->toDateString()" :to="$to->toDateString()" />
        <x-select-field name="project" :label="false" class="select-sm w-64">
            <option value="">{{ __('Alle Projekte') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" @selected($projectId === (int) $p->id)>{{ $p->name }}</option>
            @endforeach
        </x-select-field>
    </x-filter-bar>

    @if ($allRows === [])
        <x-card>
            <x-empty-state icon="flag"
                           :title="__('Keine Projekt-Sollzeiten im Zeitraum.')"
                           :message="__('Solldaten entstehen aus den Sollzeiten am Projekt (Zeitreiter, „Sollzeiten pflegen“).')" />
        </x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Plan')" :value="$fmtH($totals['plan_minutes'])" tone="info" :href="route('reports.plan-ist.project-persons', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" />
            <x-kpi-tile :label="__('Ist')" :value="$fmtH($totals['actual_minutes'])" tone="primary" :href="route('reports.plan-ist.project-persons', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" />
            <x-kpi-tile :label="__('Differenz')" :value="$fmtH($totals['delta_minutes'])" :tone="$totals['delta_minutes'] > 0 ? 'warning' : 'success'" :href="route('reports.plan-ist.project-persons', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" />
            <x-kpi-tile :label="__('Tage unter Soll')" :value="$totals['open_days']" :hint="__('offen oder bestätigt, :total im Zeitraum', ['total' => $totals['shortfall_days']])" :tone="$totals['open_days'] > 0 ? 'warning' : 'success'" :href="route('reports.plan-ist.project-persons', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" />
        </div>

        <x-card :title="__('Projekt je Person')" icon="flag">
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Projekt') }}</th>
                        <th>{{ __('Person') }}</th>
                        <th class="text-right">{{ __('Soll (h)') }}</th>
                        <th class="text-right">{{ __('Gebucht (h)') }}</th>
                        <th class="text-right">{{ __('Differenz (h)') }}</th>
                        <th class="text-right">{{ __('Tage unter Soll') }}</th>
                        @if ($hasVisits)
                            <th class="text-right">{{ __('Aufenthalt (h)') }}</th>
                        @endif
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['project'] }}</td>
                        <td>{{ $row['user'] }}</td>
                        <td class="text-right">{{ $fmtH($row['plan_minutes']) }}</td>
                        <td class="text-right">{{ $fmtH($row['actual_minutes']) }}</td>
                        <td class="text-right {{ $row['delta_minutes'] > 0 ? 'text-warning' : '' }}">{{ $fmtH($row['delta_minutes']) }}</td>
                        <td class="text-right">{{ $row['open_days'] }}@if ($row['shortfall_days'] > $row['open_days']) <span class="text-muted">/ {{ $row['shortfall_days'] }}</span>@endif</td>
                        @if ($hasVisits)
                            <td class="text-right">{{ $row['visit_minutes'] === null ? '—' : $fmtH($row['visit_minutes']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$rows" />
        </x-card>
    @endif
</x-page-shell>
@endsection

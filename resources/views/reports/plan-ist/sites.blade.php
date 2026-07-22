{{--
  Created on   : Sat Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sites.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist Standort-Dimension (A14 · MVP-333): Ist-Verteilung der ortsbasiert
  erfassten Zeiten (Geofences je Standort → Ortsbesuche). Solldaten je
  Standort existieren im Datenmodell nicht (Schichten/Arbeitszeitmodelle sind
  nicht standortbezogen) — die Lücke wird bewusst ausgewiesen statt ein neues
  Planungsmodell zu erfinden.
--}}

@extends('layouts.app')
@section('title', __('Plan/Ist — Standorte'))
@section('nav-title', __('Plan/Ist — Standorte'))

@section('content')
@php
    $fmtH = fn (int $minutes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes / 60, 1, withThousandsSeparator: true) . ' h';
    $chartSeries = collect($rows->items())
        ->map(fn (array $r): array => [
            'x' => $r['name'],
            'y' => round($r['actual_minutes'] / 60, 1),
        ])
        ->values()
        ->all();
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.plan-ist._dimensions')

    <div class="alert alert-info alert-soft text-sm">
        <x-icon name="info" />
        <span>{{ __('Für Standorte existieren keine Solldaten (Schichten und Arbeitszeitmodelle sind nicht standortbezogen) — diese Sicht zeigt die Ist-Verteilung der ortsbasiert erfassten Zeiten.') }}</span>
    </div>

    {{-- Gemeinsame Filterleiste statt freiem GET-Formular (Vollaudit 2026-07, N58). --}}
    <x-filter-bar :action="route('reports.plan-ist.sites')" :reset="route('reports.plan-ist.sites')">
        <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" class="w-72 shrink-0" />
    </x-filter-bar>

    @if ($rows->total() === 0)
        <x-card>
            <x-empty-state icon="location_on"
                           :title="__('Keine ortsbasiert erfassten Zeiten im Zeitraum.')"
                           :message="__('Standort-Zuordnungen entstehen über die standortbasierte Zeiterfassung (Geofences je Standort).')" />
        </x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('Ist')" :value="$fmtH($totals['actual_minutes'])" tone="primary" />
            <x-kpi-tile :label="__('Ortsbesuche')" :value="$totals['visits']" tone="neutral" format="int" />
            <x-kpi-tile :label="__('Personen')" :value="$totals['users']" tone="neutral" format="int" />
        </div>

        <x-charts.bar :title="__('Ist-Zeiten je Standort')"
                      :unit="__('Stunden')"
                      :series="$chartSeries"
                      :x-label="__('Standort')"
                      :y-label="__('Ist (h)')" />

        <x-card :title="__('Je Standort')" icon="location_on">
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Standort') }}</th>
                        <th>{{ __('Kunde') }}</th>
                        <th class="text-right">{{ __('Ortsbesuche') }}</th>
                        <th class="text-right">{{ __('Personen') }}</th>
                        <th class="text-right">{{ __('Ist (h)') }}</th>
                        <th class="text-right">{{ __('Anteil') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">
                            {{ $row['name'] }}
                            @if ($row['site_id'] === null)
                                <x-status-badge size="xs" tone="neutral">{{ __('Geofence ohne Standort') }}</x-status-badge>
                            @endif
                        </td>
                        <td>{{ $row['customer'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['visits'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['users'] }}</td>
                        <td class="text-right tabular-nums">{{ $fmtH($row['actual_minutes']) }}</td>
                        <td class="text-right tabular-nums">
                            {{ $totals['actual_minutes'] > 0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['actual_minutes'] / $totals['actual_minutes'] * 100, 1, withThousandsSeparator: true) . ' %' : '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @endif
</x-page-shell>
@endsection

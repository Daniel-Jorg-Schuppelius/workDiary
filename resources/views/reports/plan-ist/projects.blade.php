{{--
  Created on   : Sat Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : projects.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist Projekt-Dimension (A14 · MVP-333, Konzept §2.2): Soll aus den
  geplanten Minuten der im Zeitraum überlappenden Aufträge, Ist aus den
  Zeitbuchungen; Projekte ohne geplante Aufträge sind als „ohne Solldaten"
  markiert (kein Alarm, Konzept-Status noPlan).
--}}

@extends('layouts.app')
@section('title', __('Plan/Ist — Projekte'))
@section('nav-title', __('Plan/Ist — Projekte'))

@section('content')
@php
    $fmtH = fn (int $minutes): string => number_format($minutes / 60, 1, ',', '.') . ' h';
    // Diagramm: Top-Projekte nach Ist-Minuten (zwei Serien Plan/Ist).
    $chartSeries = collect($allRows)
        ->sortByDesc('actual_minutes')
        ->take(12)
        ->map(fn (array $r): array => [
            'x' => $r['name'],
            'y' => round($r['plan_minutes'] / 60, 1),
            'y2' => round($r['actual_minutes'] / 60, 1),
        ])
        ->values()
        ->all();
@endphp
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Plan/Ist — Projekte') }}</x-slot:title>
        <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }}</x-slot:subtitle>
    </x-page-toolbar>

    @include('reports.plan-ist._dimensions')

    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" class="w-72" />
            <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Anwenden') }}</x-icon-btn>
        </form>
    </x-card>

    @if ($allRows === [])
        <x-card>
            <x-empty-state icon="folder_special"
                           :title="__('Keine Projektzeiten oder geplanten Aufträge im Zeitraum.')"
                           :message="__('Solldaten entstehen aus geplanten Minuten der Aufträge (Auftragsplanung).')" />
        </x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Plan')" :value="$fmtH($totals['plan_minutes'])" tone="info" />
            <x-kpi-tile :label="__('Ist')" :value="$fmtH($totals['actual_minutes'])" tone="primary" />
            <x-kpi-tile :label="__('Differenz')" :value="$fmtH($totals['delta_minutes'])" :tone="$totals['delta_minutes'] > 0 ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('Abrechenbar (Ist)')" :value="$fmtH($totals['billable_minutes'])" tone="neutral" />
        </div>

        <x-charts.bar :title="__('Top-Projekte: Plan vs. Ist')"
                      :unit="__('Stunden')"
                      :series="$chartSeries"
                      :x-label="__('Projekt')"
                      :y-label="__('Plan (h)')"
                      :y2-label="__('Ist (h)')" />

        <x-card :title="__('Je Projekt')" icon="folder_special">
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Projekt') }}</th>
                        <th>{{ __('Kunde') }}</th>
                        <th class="text-right">{{ __('Aufträge (geplant)') }}</th>
                        <th class="text-right">{{ __('Plan (h)') }}</th>
                        <th class="text-right">{{ __('Ist (h)') }}</th>
                        <th class="text-right">{{ __('Abrechenbar (h)') }}</th>
                        <th class="text-right">{{ __('Differenz (h)') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">{{ $row['name'] }}</td>
                        <td>{{ $row['customer'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['orders'] }} ({{ $row['planned_orders'] }})</td>
                        <td class="text-right tabular-nums">
                            @if ($row['no_plan'])<span class="text-base-content/40">—</span>
                            @else{{ $fmtH($row['plan_minutes']) }}@endif
                        </td>
                        <td class="text-right tabular-nums">{{ $fmtH($row['actual_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmtH($row['billable_minutes']) }}</td>
                        <td class="text-right tabular-nums {{ ! $row['no_plan'] && $row['delta_minutes'] > 0 ? 'text-warning' : '' }}">
                            @if ($row['no_plan'])<span class="text-base-content/40">—</span>
                            @else{{ $fmtH($row['delta_minutes']) }}@endif
                        </td>
                        <td>
                            @if ($row['no_plan'])
                                {{-- Konzept §2.2: noPlan = kein Alarm, nur Kennzeichnung. --}}
                                <x-status-badge size="xs" tone="neutral">{{ __('ohne Solldaten') }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-base-content/60">
                {{ __('Soll = geplante Minuten der Aufträge im Zeitraum; Budget-Vergleiche (Zeitbudget/€) liefert der Wirtschaftlichkeits-Report.') }}
            </p>
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @endif
</x-page-shell>
@endsection

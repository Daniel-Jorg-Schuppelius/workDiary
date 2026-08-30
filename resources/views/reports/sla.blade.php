{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sla.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('sla.report.title'))
@section('nav-title', __('sla.report.title'))

@section('content')
@php
    $pct = fn (?float $v) => $v !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %' : '–';
    $kindLabels = [
        'responseTime'   => __('enums.sla.violationKind.responseTime'),
        'resolutionTime' => __('enums.sla.violationKind.resolutionTime'),
    ];
    $prioLabels = [
        'low'    => __('Niedrig'),
        'normal' => __('Normal'),
        'high'   => __('Hoch'),
        'urgent' => __('Dringend'),
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('sla.report.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.sla', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.sla', array_merge($standardFilters->toQueryParams(), ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.sla', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.sla')" :reset="route('reports.sla')">
        @include('reports._standard_filters', ['idPrefix' => 'sla'])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('SLA-Erfüllung (%) :per', ['per' => $periodPhrase])" unit="%" :series="$complianceSeries" :median="$complianceMedian" :x-label="$periodAxis" :y-label="__('Erfüllung (%)')" />
        <x-charts.bar-h :title="__('Verletzungen je Kunde (Top 15)')" :unit="__('Verletzungen')" :series="$violationCustomerSeries" :x-label="__('Kunde')" :y-label="__('Anzahl')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('sla.report.total_tickets')" :value="$total_tickets" />
        <x-kpi-tile :label="__('sla.report.violations')" :value="$violation_count"
                    :tone="$violation_count > 0 ? 'error' : 'success'" />
        <x-kpi-tile :label="__('sla.report.met')" :value="$met_count" tone="success" />
        <x-kpi-tile :label="__('sla.report.compliance_rate')" :value="$pct($compliance_rate)"
                    :tone="$compliance_rate !== null && $compliance_rate < 0.9 ? 'warning' : 'neutral'" />
    </div>

    {{-- Feature 002: Zielwert SLA-Einhaltungsquote (Soll/Ist) --}}
    @if(($compliance_target ?? null) !== null)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-medium">{{ __('reporting.target.metric.slaComplianceRate') }}:</span>
            <x-reports.target-badge :eval="$compliance_target" />
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.by_kind') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('sla.report.kind') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($by_kind as $kind => $c)
                    <tr><td>{{ $kindLabels[$kind] ?? $kind }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.by_priority') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Priorität') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($by_priority as $p => $c)
                    <tr><td>{{ $prioLabels[$p] ?? $p }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.by_cause') }}</h3>
            @if (empty($by_cause))
                <p class="text-sm text-muted">{{ __('sla.report.no_causes') }}</p>
            @else
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('sla.report.cause') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($by_cause as $cause => $c)
                        <tr><td>{{ $cause }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.by_customer') }}</h3>
        @if (empty($by_customer))
            <p class="text-sm text-muted">{{ __('sla.report.no_violations') }}</p>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($by_customer as $c)
                    <tr><td>{{ $c['name'] }}</td><td class="text-right tabular-nums">{{ $c['count'] }}</td></tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- SLA-Inklusivzeit-Kontingente (Feature 010 → Rang 44): Verbrauch je Vertrag
         in der Periode, in der das Berichtsende liegt. --}}
    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.quotas_heading') }}</h3>
        @if (empty($quotas))
            <p class="text-sm text-muted">{{ __('sla.report.no_quotas') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($quotas as $q)
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium">{{ $q['contract'] }}</span>
                            <span class="text-xs tabular-nums text-muted">{{ $q['percentage'] }} %</span>
                        </div>
                        <progress class="progress w-full {{ $q['threshold_reached'] ? 'progress-warning' : 'progress-success' }}"
                                  value="{{ min(100, $q['percentage']) }}" max="100"></progress>
                        <div class="mt-0.5 text-xs tabular-nums text-muted">
                            {{ __('sla.report.quota_usage', [
                                'consumed' => number_format($q['consumed'] / 60, 1),
                                'included' => number_format($q['included'] / 60, 1),
                                'period' => $q['period_key'],
                            ]) }}
                            @if ($q['over'] > 0)
                                <span class="text-error"> · {{ __('sla.report.quota_over', ['min' => $q['over']]) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('sla.report.violation_list') }}</h3>
        @if ($violations->isEmpty())
            <x-empty-state icon="verified"
                           :title="__('sla.report.no_violations')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Ticket') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('sla.report.kind') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('sla.report.target') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('sla.report.breached_at') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('sla.report.overdue') }}</x-table.th>
                        <th>{{ __('sla.report.status') }}</th>
                        @if ($canManage)<th></th>@endif
                    </tr>
                </x-slot:head>
                @foreach ($violations as $v)
                    <tr class="hover">
                        <td class="font-mono text-xs">
                            @if ($v->serviceTicket)
                                <a href="{{ route('service-tickets.show', $v->serviceTicket) }}" class="link link-hover">{{ $v->serviceTicket->ticket_no }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $v->kind->label() }}</td>
                        <td class="text-base-content/70">{{ $v->serviceTicket?->customer?->name ?: '—' }}</td>
                        <td class="text-base-content/70 text-xs">{{ $v->target_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</td>
                        <td class="text-base-content/70 text-xs">{{ $v->breached_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $v->overdue_minutes }}</td>
                        <td>
                            @if ($v->isAcknowledged())
                                <x-status-badge tone="success" size="sm">{{ __('sla.report.acknowledged_badge') }}</x-status-badge>
                            @else
                                <x-status-badge tone="error" size="sm" outline>{{ __('sla.report.open_badge') }}</x-status-badge>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-right">
                                @if (! $v->isAcknowledged())
                                    <form method="POST" action="{{ route('reports.sla.acknowledge', $v) }}" class="flex items-center gap-1 justify-end">
                                        @csrf
                                        <input aria-label="{{ __('sla.report.cause') }}" type="text" name="cause" maxlength="191"
                                               class="input input-xs input-bordered w-32"
                                               placeholder="{{ __('sla.report.cause') }}">
                                        <button class="btn btn-xs" type="submit">{{ __('sla.report.acknowledge_btn') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

@extends('layouts.app')
@section('title', __('compliance.report.title'))
@section('nav-title', __('compliance.report.nav'))

@section('content')
@php
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $tone = fn (string $sev) => $sev === \App\Services\Compliance\AttendanceComplianceFinding::SEVERITY_ERROR ? 'error' : 'warning';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('compliance.report.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="fact_check" tone="outline" size="sm"
                            :href="route('reports.compliance.history')"
                            show-label>{{ __('compliance.history.nav') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.arbzg-compliance', array_merge($standardFilters->toQueryParams(), array_filter(['kind' => $kindFilter ?: null, 'export' => 'csv'])))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.arbzg-compliance', array_merge($standardFilters->toQueryParams(), array_filter(['kind' => $kindFilter ?: null, 'export' => 'pdf'])))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.arbzg-compliance')" :reset="route('reports.arbzg-compliance')">
        @include('reports._standard_filters', ['idPrefix' => 'arbzg'])
        <x-filter-field :label="__('compliance.report.filter.kind')" for="rep-kind">
            <select id="rep-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('compliance.report.filter.all') }}</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind }}" @selected($kindFilter === $kind)>{{ __('compliance.report.kind.' . $kind) }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.stacked-bar :title="__('Befunde je Monat nach Verstoßart')" :unit="__('Befunde')"
                              :series="$monthlyKindSeries" :bands="$kindBands" :x-label="__('Monat')" />
        <x-charts.heatmap
            :title="__('Befunde je Mitarbeiter und Monat')"
            :unit="__('Befunde')"
            :rows="$heatmapRows"
            :col-labels="$monthLabels"
            :x-label="__('Mitarbeiter')"
        />
    </div>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        <x-kpi-tile :label="__('compliance.report.kpi.total')" :value="$summary['total']"
                    :tone="$summary['total'] > 0 ? 'error' : 'success'" />
        <x-kpi-tile :label="__('compliance.report.kpi.employees')" :value="$summary['employees']" />
        @foreach ($kinds as $kind)
            <x-kpi-tile :label="__('compliance.report.kind.' . $kind)" :value="$summary['by_kind'][$kind] ?? 0"
                        :tone="($summary['by_kind'][$kind] ?? 0) > 0 ? 'warning' : 'neutral'" />
        @endforeach
    </div>

    <x-card class="mt-2">
        <p class="text-xs text-base-content/60">
            {{ __('compliance.report.thresholds_note', [
                'daily' => $thresholds[\App\Services\Compliance\AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS] ?? '',
                'rest' => $thresholds[\App\Services\Compliance\AttendanceComplianceChecker::KIND_REST_PERIOD] ?? '',
                'weekly' => $thresholds[\App\Services\Compliance\AttendanceComplianceChecker::KIND_MAX_WEEKLY_HOURS] ?? '',
            ]) }}
        </p>
    </x-card>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">verified</span>'
                           :title="__('compliance.report.empty')" />
        @else
            @foreach ($rows as $r)
                <div class="mb-4">
                    <h3 class="mb-2 text-sm font-semibold">{{ $r['user']->name }}</h3>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('compliance.report.col.date') }}</x-table.th>
                                <x-table.th>{{ __('compliance.report.col.kind') }}</x-table.th>
                                <x-table.th align="right">{{ __('compliance.report.col.value') }}</x-table.th>
                                <x-table.th align="right">{{ __('compliance.report.col.threshold') }}</x-table.th>
                                <x-table.th>{{ __('compliance.report.col.severity') }}</x-table.th>
                                <x-table.th></x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($r['findings'] as $f)
                            <tr>
                                <td class="tabular-nums">
                                    {{ \Carbon\Carbon::parse($f['date'])->fdate() }}
                                    @if ($f['corrected'])
                                        <span class="badge badge-ghost badge-sm ml-1" title="{{ __('compliance.report.corrected_hint') }}">
                                            <span class="material-symbols-outlined text-[14px] align-middle">history</span>
                                            {{ __('compliance.report.corrected') }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ __('compliance.report.kind.' . $f['kind']) }}</td>
                                <td class="text-right tabular-nums font-semibold">{{ $fmtMin((int) $f['value']) }}</td>
                                <td class="text-right tabular-nums text-base-content/60">{{ $fmtMin((int) $f['threshold']) }}</td>
                                <td><x-status-badge :tone="$tone($f['severity'])" size="sm">{{ __('compliance.report.severity.' . $f['severity']) }}</x-status-badge></td>
                                <td class="text-right">
                                    <a class="link link-hover text-xs"
                                       href="{{ route('day-close.show', ['date' => $f['date'], 'user' => $f['user_sqid']]) }}">
                                        {{ __('compliance.report.drilldown') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            @endforeach
        @endif
    </x-card>
</x-page-shell>
@endsection

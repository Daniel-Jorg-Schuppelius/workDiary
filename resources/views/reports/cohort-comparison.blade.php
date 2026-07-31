{{--
  Created on   : Sun Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cohort-comparison.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('reporting.cohort.title'))
@section('nav-title', __('reporting.cohort.title'))

@section('content')
@php
    $pct = fn($v): string => $v === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' %';
    $deltaCell = function ($delta, $improved): string {
        if ($delta === null) {
            return '–';
        }
        $str = ($delta > 0 ? '+' : '') . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $delta, 2, withThousandsSeparator: true) . ' %';
        $tone = $improved === true ? 'text-success' : ($improved === false ? 'text-error' : '');
        return '<span class="' . $tone . '">' . $str . '</span>';
    };
    $qualSqid = $qualificationId !== null ? \App\Support\Sqid::encode(\App\Models\Qualification::class, $qualificationId) : null;
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('reporting.cohort.subtitle')">
            <x-slot:actions>
                @if($result !== null)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="route('reports.cohort-comparison', array_merge($standardFilters->toQueryParams(), array_filter(['qualification_id' => $qualSqid, 'metric' => $metric, 'window' => $window, 'export' => 'csv'])))"
                                show-label>CSV</x-icon-btn>
                @endif
                <x-help-button topic="reports.cohort-comparison" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.cohort-comparison')" :reset="route('reports.cohort-comparison')">
        @include('reports._standard_filters', ['idPrefix' => 'cohort'])
        <x-filter-field show-label :label="__('reporting.cohort.qualification')" for="ch-qual">
            <select id="ch-qual" name="qualification_id" class="select select-sm select-bordered">
                <option value="">{{ __('reporting.cohort.choose') }}</option>
                @foreach($qualifications as $q)
                    <option value="{{ $q->sqid }}" @selected($qualSqid === $q->sqid)>{{ $q->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field show-label :label="__('reporting.cohort.metric_label')" for="ch-metric">
            <select id="ch-metric" name="metric" class="select select-sm select-bordered">
                @foreach($metricOptions as $key => $labelKey)
                    <option value="{{ $key }}" @selected($metric === $key)>{{ __($labelKey) }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field show-label :label="__('reporting.cohort.window')" for="ch-window">
            <input id="ch-window" name="window" type="number" min="7" max="365" value="{{ $window }}"
                   class="input input-sm input-bordered w-24" />
        </x-filter-field>
    </x-filter-bar>

    <div role="alert" class="alert alert-info mb-4 text-sm">
        <span class="material-symbols-outlined" aria-hidden="true">info</span>
        <div>{{ __('reporting.cohort.data_note') }}</div>
    </div>

    <div class="grid gap-3 xl:grid-cols-2 mb-4">
        <x-charts.bar :title="__('Vorher vs. nachher je Mitarbeitendem')" unit="%" :series="$beforeAfterSeries" :y2-label="__('reporting.cohort.after')" :x-label="__('reporting.cohort.member')" :y-label="__($metricOptions[$metric])" />
        <x-charts.line :title="__('Kohortenverlauf (Wochen vor/nach Erwerb)')" unit="%" :series="$weeklySeries" :x-label="__('Woche relativ zum Erwerb')" :y-label="__($metricOptions[$metric])" />
    </div>

    @if($result === null)
        <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' :title="__('reporting.cohort.choose')" />
    @else
        @php $agg = $result['aggregate']; @endphp
        <div class="grid gap-3 grid-cols-2 sm:grid-flow-col sm:auto-cols-fr mb-4">
            <x-kpi-tile :label="__('reporting.cohort.before')" :value="$pct($agg['before'])" />
            <x-kpi-tile :label="__('reporting.cohort.after')" :value="$pct($agg['after'])" />
            <x-kpi-tile :label="__('reporting.cohort.delta')"
                        :value="$agg['delta'] === null ? '–' : (($agg['delta'] > 0 ? '+' : '') . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $agg['delta'], 2, withThousandsSeparator: true) . ' %')"
                        :tone="$agg['delta'] === null ? 'neutral' : ($agg['delta'] > 0 xor $metric === 'reworkShare' ? 'success' : 'error')" />
            <x-kpi-tile :label="__('reporting.cohort.improved_count')" :value="$agg['improvedCount'] . ' / ' . $agg['membersWithDate']" tone="info" />
        </div>

        @if($agg['membersWithoutDate'] > 0)
            <div role="alert" class="alert alert-warning mb-4 text-sm">
                <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                <div>{{ $agg['membersWithoutDate'] }} {{ __('reporting.cohort.members_without_date') }} — {{ __('reporting.cohort.no_date_hint') }}</div>
            </div>
        @endif

        <x-card>
            <div class="mb-3 text-sm font-semibold">{{ $qualification?->name }} — {{ __($metricOptions[$metric]) }}</div>
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('reporting.cohort.member') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('reporting.cohort.acquired_on') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('reporting.cohort.before') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('reporting.cohort.after') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('reporting.cohort.delta') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach($result['members'] as $m)
                    <tr>
                        <td class="font-medium">{{ $m['userName'] }}</td>
                        <td>
                            @if($m['acquiredOn'] === null)
                                <span class="text-warning text-xs">{{ __('reporting.cohort.no_date') }}</span>
                            @else
                                {{ \Carbon\Carbon::parse($m['acquiredOn'])->format('d.m.Y') }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $pct($m['before']) }}</td>
                        <td class="text-right tabular-nums">{{ $pct($m['after']) }}</td>
                        <td class="text-right tabular-nums">{!! $deltaCell($m['delta'], $m['improved']) !!}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

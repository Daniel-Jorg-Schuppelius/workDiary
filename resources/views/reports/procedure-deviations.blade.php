{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : procedure-deviations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('procedure.report.title'))
@section('nav-title', __('procedure.report.nav'))

@section('content')
@php
    /** @var array{rows: list<array<string, mixed>>, total: int, byType: array<string, int>, bySeverity: array<string, int>, followUpCount: int, followUpRate: ?float, riskAcceptedCount: int, avgDecisionHours: ?float, topTemplates: list<array{templateId: ?int, templateName: string, count: int}>} $result */
    $fmt = fn (?float $v, int $d = 1): string => $v === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, $d);
    $severityTone = fn (\App\Enums\Procedure\ProcedureDeviationSeverity $s): string => match ($s) {
        \App\Enums\Procedure\ProcedureDeviationSeverity::Critical => 'error',
        \App\Enums\Procedure\ProcedureDeviationSeverity::High => 'warning',
        \App\Enums\Procedure\ProcedureDeviationSeverity::Medium => 'info',
        default => 'neutral',
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('procedure.report.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.procedure-deviations', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.procedure-deviations', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.procedure-deviations', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.procedure-deviations')" :reset="route('reports.procedure-deviations')">
        @include('reports._standard_filters', ['idPrefix' => 'pdev'])
        <x-filter-field :label="__('procedure.report.filter.template')" for="pdev-template" class="min-w-44">
            <select id="pdev-template" name="template" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('procedure.report.filter.all_templates') }}</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->sqid }}" @selected($templateId === $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('procedure.report.col.type')" for="pdev-type" class="min-w-40">
            <select id="pdev-type" name="type" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('procedure.report.filter.all_types') }}</option>
                @foreach (\App\Enums\Procedure\ProcedureDeviationType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('procedure.report.col.severity')" for="pdev-severity" class="min-w-36">
            <select id="pdev-severity" name="severity" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('procedure.report.filter.all_severities') }}</option>
                @foreach (\App\Enums\Procedure\ProcedureDeviationSeverity::cases() as $case)
                    <option value="{{ $case->value }}" @selected($severity === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('procedure.report.filter.risk')" for="pdev-risk" class="min-w-36">
            <select id="pdev-risk" name="risk" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="" @selected($risk === null)>{{ __('procedure.report.filter.any') }}</option>
                <option value="yes" @selected($risk === true)>{{ __('procedure.report.filter.risk_yes') }}</option>
                <option value="no" @selected($risk === false)>{{ __('procedure.report.filter.risk_no') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('procedure.report.filter.follow_up')" for="pdev-follow-up" class="min-w-40">
            <select id="pdev-follow-up" name="follow_up" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="" @selected($followUp === null)>{{ __('procedure.report.filter.any') }}</option>
                <option value="yes" @selected($followUp === true)>{{ __('procedure.report.filter.follow_up_yes') }}</option>
                <option value="no" @selected($followUp === false)>{{ __('procedure.report.filter.follow_up_no') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        <x-kpi-tile :label="__('procedure.report.kpi.total')" :value="$result['total']" tone="primary" />
        <x-kpi-tile :label="__('procedure.report.kpi.critical')" :value="$result['bySeverity']['critical'] ?? 0" tone="error" />
        <x-kpi-tile :label="__('procedure.report.kpi.follow_up_rate')" :value="$result['followUpRate'] !== null ? $fmt($result['followUpRate']) . ' %' : '–'"
                    tone="info" :hint="__('procedure.report.kpi.follow_up_hint', ['count' => $result['followUpCount']])" />
        <x-kpi-tile :label="__('procedure.report.kpi.decision_hours')" :value="$fmt($result['avgDecisionHours'])"
                    tone="neutral" :hint="__('procedure.report.kpi.decision_hint', ['count' => $result['riskAcceptedCount']])" />
    </div>

    <div class="chart-grid mt-4 grid gap-3 xl:grid-cols-2">
        <x-charts.bar-h :title="__('procedure.report.chart.by_type')" :unit="__('procedure.report.unit')" :series="$typeSeries"
                        :x-label="__('procedure.report.col.type')" :y-label="__('procedure.report.unit')" />
        <x-charts.stacked-bar :title="__('procedure.report.chart.by_period', ['per' => $periodPhrase])" :unit="__('procedure.report.unit')"
                              :series="$severitySeries" :bands="$severityBands" :x-label="$periodAxis" />
    </div>
    <x-charts.bar-h :title="__('procedure.report.chart.top_templates')" :unit="__('procedure.report.unit')" :series="$templateSeries"
                    :x-label="__('procedure.report.col.template')" :y-label="__('procedure.report.unit')"
                    :note="__('procedure.report.chart.top_templates_note')" />

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-muted">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if ($rows === [])
            <x-empty-state icon="rule" :title="__('procedure.report.empty')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('procedure.report.col.date') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.template') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.step') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.type') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.severity') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.follow_up') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('procedure.report.col.risk_accepted') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('procedure.report.col.decision_hours') }}</x-table.th>
                        <x-table.th></x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap tabular-nums">{{ \App\Support\CarbonFmt::fdatetime($row['createdAt']) }}</td>
                        <td class="font-medium">{{ $row['templateName'] }}</td>
                        <td>{{ $row['stepLabel'] }}</td>
                        <td>{{ $row['type']->label() }}</td>
                        <td><x-status-badge :tone="$severityTone($row['severity'])">{{ $row['severity']->label() }}</x-status-badge></td>
                        <td>
                            @if ($row['followUpKind'] === 'open_issue')
                                {{ __('procedure.report.follow_up.open_issue') }}
                            @elseif ($row['followUpKind'] === 'diary_entry')
                                {{ __('procedure.report.follow_up.diary_entry') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap tabular-nums">{{ $row['riskAcceptedAt'] !== null ? \App\Support\CarbonFmt::fdatetime($row['riskAcceptedAt']) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['decisionHours'] !== null ? $fmt($row['decisionHours']) : '—' }}</td>
                        <td class="text-right">
                            @if ($row['runSqid'] !== null)
                                <x-icon-btn icon="open_in_new" :href="route('procedure-runs.show', $row['runSqid'])" :label="__('procedure.report.open_run')" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

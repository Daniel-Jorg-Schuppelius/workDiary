{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : procedure-deviations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('procedure.report.title'))
@section('pdf-heading', __('procedure.report.title'))

@section('pdf-table')
    @php
        /** @var array{rows: list<array<string, mixed>>, total: int, byType: array<string, int>, bySeverity: array<string, int>, followUpCount: int, followUpRate: ?float, riskAcceptedCount: int, avgDecisionHours: ?float, topTemplates: list<array{templateId: ?int, templateName: string, count: int}>} $result */
        $fmt = fn (?float $v): string => $v === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1);
    @endphp
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('procedure.report.kpi.total') }}: {{ $result['total'] }} ·
        {{ __('procedure.report.kpi.critical') }}: {{ $result['bySeverity']['critical'] ?? 0 }} ·
        {{ __('procedure.report.kpi.follow_up_rate') }}: {{ $result['followUpRate'] !== null ? $fmt($result['followUpRate']) . ' %' : '–' }} ·
        {{ __('procedure.report.kpi.decision_hours') }}: {{ $fmt($result['avgDecisionHours']) }}
    </p>

    @include('reports.pdf.charts._chart')

    <h2>{{ __('procedure.report.chart.top_templates') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('procedure.report.col.template') }}</th>
                <th class="num">{{ __('procedure.report.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result['topTemplates'] as $template)
                <tr>
                    <td>{{ $template['templateName'] }}</td>
                    <td class="num">{{ $template['count'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2">–</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('procedure.report.list') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('procedure.report.col.date') }}</th>
                <th>{{ __('procedure.report.col.template') }}</th>
                <th>{{ __('procedure.report.col.step') }}</th>
                <th>{{ __('procedure.report.col.type') }}</th>
                <th>{{ __('procedure.report.col.severity') }}</th>
                <th>{{ __('procedure.report.col.follow_up') }}</th>
                <th>{{ __('procedure.report.col.risk_accepted') }}</th>
                <th class="num">{{ __('procedure.report.col.decision_hours') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result['rows'] as $row)
                <tr>
                    <td>{{ \App\Support\CarbonFmt::fdatetime($row['createdAt']) }}</td>
                    <td>{{ $row['templateName'] }}</td>
                    <td>{{ $row['stepLabel'] }}</td>
                    <td>{{ $row['type']->label() }}</td>
                    <td>{{ $row['severity']->label() }}</td>
                    <td>{{ $row['followUpKind'] === 'open_issue' ? __('procedure.report.follow_up.open_issue') : ($row['followUpKind'] === 'diary_entry' ? __('procedure.report.follow_up.diary_entry') : '–') }}</td>
                    <td>{{ $row['riskAcceptedAt'] !== null ? \App\Support\CarbonFmt::fdatetime($row['riskAcceptedAt']) : '–' }}</td>
                    <td class="num">{{ $row['decisionHours'] !== null ? $fmt($row['decisionHours']) : '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('procedure.report.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

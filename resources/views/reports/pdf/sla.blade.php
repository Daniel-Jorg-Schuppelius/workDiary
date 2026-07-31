@extends('reports.pdf.layout')

@section('pdf-title', 'SLA – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('sla.report.title'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        $pct = fn (?float $v) => $v !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %' : '–';
        $kindLabels = [
            'responseTime'   => __('enums.sla.violationKind.responseTime'),
            'resolutionTime' => __('enums.sla.violationKind.resolutionTime'),
        ];
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('sla.report.total_tickets') }}</div><div class="value">{{ $total_tickets }}</div></td>
            <td><div class="label">{{ __('sla.report.violations') }}</div><div class="value">{{ $violation_count }}</div></td>
            <td><div class="label">{{ __('sla.report.met') }}</div><div class="value">{{ $met_count }}</div></td>
            <td><div class="label">{{ __('sla.report.compliance_rate') }}</div><div class="value">{{ $pct($compliance_rate) }}</div></td>
        </tr>
    </table>

    <h2>{{ __('sla.report.by_kind') }}</h2>
    <table class="data">
        <thead><tr><th>{{ __('sla.report.kind') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
        <tbody>
            @foreach ($by_kind as $kind => $c)
                <tr><td>{{ $kindLabels[$kind] ?? $kind }}</td><td class="right">{{ $c }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('sla.report.by_priority') }}</h2>
    <table class="data">
        <thead><tr><th>{{ __('Priorität') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
        <tbody>
            @foreach ($by_priority as $p => $c)
                <tr><td>{{ $p }}</td><td class="right">{{ $c }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('sla.report.by_customer') }}</h2>
    <table class="data">
        <thead><tr><th>{{ __('Kunde') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
        <tbody>
            @forelse ($by_customer as $c)
                <tr><td>{{ $c['name'] }}</td><td class="right">{{ $c['count'] }}</td></tr>
            @empty
                <tr><td colspan="2">{{ __('sla.report.no_violations') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($by_cause))
        <h2>{{ __('sla.report.by_cause') }}</h2>
        <table class="data">
            <thead><tr><th>{{ __('sla.report.cause') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
            <tbody>
                @foreach ($by_cause as $cause => $c)
                    <tr><td>{{ $cause }}</td><td class="right">{{ $c }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

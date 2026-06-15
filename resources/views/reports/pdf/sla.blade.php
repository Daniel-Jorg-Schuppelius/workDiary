<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>SLA – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; }
    h1    { font-size: 15pt; margin: 0 0 4pt 0; }
    h2    { font-size: 11pt; margin: 12pt 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
    .kpis td { padding: 4pt 6pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 8pt; color: #666; }
    .kpis .value { font-size: 11pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
</style>
</head>
<body>
@php
    $pct = fn (?float $v) => $v !== null ? number_format($v * 100, 1, ',', '.') . ' %' : '–';
    $kindLabels = [
        'responseTime'   => __('enums.sla.violationKind.responseTime'),
        'resolutionTime' => __('enums.sla.violationKind.resolutionTime'),
    ];
@endphp

<h1>{{ __('sla.report.title') }}</h1>
<div class="meta">
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
</div>

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
</body>
</html>

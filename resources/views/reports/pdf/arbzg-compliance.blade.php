<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ __('compliance.report.title') }} – {{ $from }} bis {{ $to }}</title>
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
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
    .err  { color: #b30000; font-weight: bold; }
    .warn { color: #9a6a00; font-weight: bold; }
</style>
</head>
<body>
@php
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp

<h1>{{ __('compliance.report.title') }}</h1>
<div class="meta">
    {{ __('compliance.report.csv.date') }}:
    <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> –
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('compliance.report.kpi.total') }}: <strong>{{ $summary['total'] }}</strong> ·
    {{ now()->fdatetime() }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">{{ __('compliance.report.kpi.total') }}</div><div class="value">{{ $summary['total'] }}</div></td>
        @foreach ($kinds as $kind)
            <td><div class="label">{{ __('compliance.report.kind.' . $kind) }}</div><div class="value">{{ $summary['by_kind'][$kind] ?? 0 }}</div></td>
        @endforeach
    </tr>
</table>

@forelse ($rows as $r)
    <h2>{{ $r['user']->name }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('compliance.report.col.date') }}</th>
                <th>{{ __('compliance.report.col.kind') }}</th>
                <th class="right">{{ __('compliance.report.col.value') }}</th>
                <th class="right">{{ __('compliance.report.col.threshold') }}</th>
                <th>{{ __('compliance.report.col.severity') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($r['findings'] as $f)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($f['date'])->fdate() }}@if ($f['corrected']) · {{ __('compliance.report.corrected') }}@endif</td>
                    <td>{{ __('compliance.report.kind.' . $f['kind']) }}</td>
                    <td class="right">{{ $fmtMin((int) $f['value']) }}</td>
                    <td class="right">{{ $fmtMin((int) $f['threshold']) }}</td>
                    <td class="{{ $f['severity'] === 'error' ? 'err' : 'warn' }}">{{ __('compliance.report.severity.' . $f['severity']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p style="text-align:center; padding:12pt; color:#888;">{{ __('compliance.report.empty') }}</p>
@endforelse

</body>
</html>

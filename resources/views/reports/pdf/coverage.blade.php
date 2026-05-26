<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Coverage – {{ $from }} bis {{ $to }}</title>
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
    .totals td { border-top: 2px solid #333; font-weight: bold; }
    .neg { color: #c33; font-weight: bold; }
    .pos { color: #2a7; }
</style>
</head>
<body>
@php
    $pct = fn (float $v) => number_format($v * 100, 1, ',', '.') . ' %';
@endphp

<h1>{{ __('Coverage / Soll-Ist-Besetzung') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Schichttypen</div><div class="value">{{ $totals['shift_types'] }}</div></td>
        <td><div class="label">Soll (Personentage)</div><div class="value">{{ $totals['required'] }}</div></td>
        <td><div class="label">Ist (Personentage)</div><div class="value">{{ $totals['scheduled'] }}</div></td>
        <td><div class="label">Differenz</div><div class="value {{ $totals['gap'] < 0 ? 'neg' : 'pos' }}">{{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}</div></td>
        <td><div class="label">{{ __('Erfüllung') }}</div><div class="value">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</div></td>
        <td><div class="label">Tage unter</div><div class="value {{ $totals['days_under'] > 0 ? 'neg' : '' }}">{{ $totals['days_under'] }}</div></td>
    </tr>
</table>

<h2>{{ __('Pro Schichttyp') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Schichttyp') }}</th>
            <th class="right">{{ __('Soll') }}</th>
            <th class="right">{{ __('Ist') }}</th>
            <th class="right">{{ __('Differenz') }}</th>
            <th class="right">{{ __('Erfüllung') }}</th>
            <th class="right">{{ __('Tage unter') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r['shiftType']->name }}@if ($r['shiftType']->abbreviation) <span style="color:#666">({{ $r['shiftType']->abbreviation }})</span>@endif</td>
                <td class="right">{{ $r['required'] }}</td>
                <td class="right">{{ $r['scheduled'] }}</td>
                <td class="right {{ $r['gap'] < 0 ? 'neg' : ($r['gap'] > 0 ? 'pos' : '') }}">{{ $r['gap'] > 0 ? '+' : '' }}{{ $r['gap'] }}</td>
                <td class="right">{{ $r['fill_rate'] !== null ? $pct($r['fill_rate']) : '–' }}</td>
                <td class="right {{ $r['days_under'] > 0 ? 'neg' : '' }}">{{ $r['days_under'] }}</td>
            </tr>
        @endforeach
        <tr class="totals">
            <td>Gesamt</td>
            <td class="right">{{ $totals['required'] }}</td>
            <td class="right">{{ $totals['scheduled'] }}</td>
            <td class="right">{{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}</td>
            <td class="right">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</td>
            <td class="right">{{ $totals['days_under'] }}</td>
        </tr>
    </tbody>
</table>

@if (! empty($underfilled))
    <h2>Tage mit Unterdeckung ({{ count($underfilled) }})</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Datum') }}</th>
                <th>{{ __('Schichttyp') }}</th>
                <th class="right">{{ __('Soll') }}</th>
                <th class="right">{{ __('Ist') }}</th>
                <th class="right">{{ __('Lücke') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($underfilled as $u)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($u['date'])->translatedFormat('D, d.m.Y') }}</td>
                    <td>{{ $u['shiftType']->name }}</td>
                    <td class="right">{{ $u['required'] }}</td>
                    <td class="right">{{ $u['scheduled'] }}</td>
                    <td class="right neg">{{ $u['gap'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>

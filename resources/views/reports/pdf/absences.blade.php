<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Urlaub &amp; Flex – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 14mm 12mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
    .kpis td { padding: 4pt 6pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 8pt; color: #666; }
    .kpis .value { font-size: 11pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    .totals td { border-top: 2px solid #333; font-weight: bold; }
    .neg { color: #c33; }
    .pos { color: #2a7; }
</style>
</head>
<body>
@php
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : ($minutes > 0 ? '+' : '');
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp

<h1>{{ __('Urlaub & Flex') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Mitarbeiter</div><div class="value">{{ $totals['users'] }}</div></td>
        <td><div class="label">Urlaub (Werktage)</div><div class="value">{{ $totals['vacation_days'] }}</div></td>
        <td><div class="label">Krank</div><div class="value">{{ $totals['sick_days'] }}</div></td>
        <td><div class="label">{{ __('Sonder / Unbezahlt') }}</div><div class="value">{{ $totals['special_days'] }} / {{ $totals['unpaid_days'] }}</div></td>
        <td><div class="label">Ausstehend</div><div class="value">{{ $totals['pending_days'] }}</div></td>
        <td><div class="label">Flex Δ</div><div class="value {{ $totals['flex_change_minutes'] < 0 ? 'neg' : 'pos' }}">{{ $fmtMin($totals['flex_change_minutes']) }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            <th class="right">Urlaub</th>
            <th class="right">Krank</th>
            <th class="right">Sonder</th>
            <th class="right">Unbezahlt</th>
            <th class="right">Ausstehend</th>
            <th class="right">Flex Δ</th>
            <th class="right">{{ __('Flex-Saldo') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r['user']->name }}</td>
                <td class="right">{{ $r['vacation_days'] }}</td>
                <td class="right">{{ $r['sick_days'] }}</td>
                <td class="right">{{ $r['special_days'] }}</td>
                <td class="right">{{ $r['unpaid_days'] }}</td>
                <td class="right">{{ $r['pending_days'] }}</td>
                <td class="right {{ $r['flex_change_minutes'] < 0 ? 'neg' : ($r['flex_change_minutes'] > 0 ? 'pos' : '') }}">{{ $fmtMin($r['flex_change_minutes']) }}</td>
                <td class="right">{{ $r['flex_balance_minutes'] !== null ? $fmtMin($r['flex_balance_minutes']) : '–' }}</td>
            </tr>
        @endforeach
        <tr class="totals">
            <td>Gesamt</td>
            <td class="right">{{ $totals['vacation_days'] }}</td>
            <td class="right">{{ $totals['sick_days'] }}</td>
            <td class="right">{{ $totals['special_days'] }}</td>
            <td class="right">{{ $totals['unpaid_days'] }}</td>
            <td class="right">{{ $totals['pending_days'] }}</td>
            <td class="right">{{ $fmtMin($totals['flex_change_minutes']) }}</td>
            <td class="right">{{ $fmtMin($totals['flex_balance_minutes']) }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>

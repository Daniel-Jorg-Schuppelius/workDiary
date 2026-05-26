<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Anwesenheit – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; }
    h1    { font-size: 15pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
    .kpis td { padding: 4pt 6pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 8pt; color: #666; }
    .kpis .value { font-size: 11pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
    .totals td { border-top: 2px solid #333; font-weight: bold; }
    .neg { color: #b30000; font-weight: bold; }
    .pos { color: #1b7c2c; }
</style>
</head>
<body>
@php
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $varClass = fn (int $v) => $v < 0 ? 'neg' : ($v > 0 ? 'pos' : '');
@endphp

<h1>{{ __('Anwesenheits-Auswertung') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Soll</div><div class="value">{{ $fmtMin($totals['target']) }}</div></td>
        <td><div class="label">Anwesend</div><div class="value">{{ $fmtMin($totals['attendance']) }}</div></td>
        <td><div class="label">Gebucht</div><div class="value">{{ $fmtMin($totals['time_entry']) }}</div></td>
        <td><div class="label">Saldo</div><div class="value {{ $varClass($totals['variance']) }}">{{ $fmtMin($totals['variance']) }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            <th class="right">Arbeitstage</th>
            <th class="right">Soll</th>
            <th class="right">Anwesend</th>
            <th class="right">Gebucht</th>
            <th class="right">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $r)
            <tr>
                <td>{{ $r['user']->name }}</td>
                <td class="right">{{ $r['workdays'] }}</td>
                <td class="right">{{ $fmtMin($r['target_minutes']) }}</td>
                <td class="right">{{ $fmtMin($r['attendance_minutes']) }}</td>
                <td class="right">{{ $fmtMin($r['time_entry_minutes']) }}</td>
                <td class="right {{ $varClass($r['variance']) }}">{{ $fmtMin($r['variance']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; padding:12pt; color:#888;">{{ __('Keine Daten.') }}</td></tr>
        @endforelse
        @if (! empty($rows))
            <tr class="totals">
                <td>Gesamt</td>
                <td></td>
                <td class="right">{{ $fmtMin($totals['target']) }}</td>
                <td class="right">{{ $fmtMin($totals['attendance']) }}</td>
                <td class="right">{{ $fmtMin($totals['time_entry']) }}</td>
                <td class="right {{ $varClass($totals['variance']) }}">{{ $fmtMin($totals['variance']) }}</td>
            </tr>
        @endif
    </tbody>
</table>

</body>
</html>

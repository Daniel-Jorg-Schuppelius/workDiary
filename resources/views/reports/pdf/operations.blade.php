<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Operations – {{ $from }} bis {{ $to }}</title>
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
    .grid { width: 100%; }
    .grid td { width: 50%; vertical-align: top; padding-right: 6pt; }
</style>
</head>
<body>
@php
    $pct = fn (?float $v) => $v !== null ? number_format($v * 100, 1, ',', '.') . ' %' : '–';
    $fmtMin = function (int $minutes): string {
        $abs = abs($minutes);
        return intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
@endphp

<h1>Operations-Auswertung</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">ServiceOrders</div><div class="value">{{ $orders['total'] }}</div></td>
        <td><div class="label">Servicezeit Σ</div><div class="value">{{ $fmtMin($orders['service_minutes']) }}</div></td>
        <td><div class="label">SO Abschluss</div><div class="value">{{ $pct($orders['completion_rate']) }}</div></td>
        <td><div class="label">Tasks (Überfällig)</div><div class="value">{{ $tasks['total'] }} ({{ $tasks['overdue'] }})</div></td>
        <td><div class="label">Tasks Abschluss</div><div class="value">{{ $pct($tasks['completion_rate']) }}</div></td>
        <td><div class="label">Touren</div><div class="value">{{ $tours['total'] }} · {{ $num($tours['planned_distance_km'], 0) }} km</div></td>
    </tr>
</table>

<table class="grid">
    <tr>
        <td>
            <h2>ServiceOrders – Status</h2>
            <table class="data">
                <thead><tr><th>Status</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($orders['by_status'] as $st => $c)
                        <tr><td>{{ $st }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <h2>ServiceOrders – Priorität</h2>
            <table class="data">
                <thead><tr><th>Priorität</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($orders['by_priority'] as $p => $c)
                        <tr><td>{{ $p }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <h2>Tasks – Status</h2>
            <table class="data">
                <thead><tr><th>Status</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($tasks['by_status'] as $st => $c)
                        <tr><td>{{ $st }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <h2>Tasks – Priorität</h2>
            <table class="data">
                <thead><tr><th>Priorität</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($tasks['by_priority'] as $p => $c)
                        <tr><td>{{ $p }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>Touren – pro Mitarbeiter</h2>
<table class="data">
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            <th class="right">Touren</th>
            <th class="right">Plan-km</th>
            <th class="right">Plan-Dauer</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tours['per_user'] as $u)
            <tr>
                <td>{{ $u['user']->name }}</td>
                <td class="right">{{ $u['count'] }}</td>
                <td class="right">{{ $num($u['distance_km'], 1) }} km</td>
                <td class="right">{{ $fmtMin($u['minutes']) }}</td>
            </tr>
        @endforeach
        <tr class="totals">
            <td>Gesamt</td>
            <td class="right">{{ $tours['total'] }}</td>
            <td class="right">{{ $num($tours['planned_distance_km'], 1) }} km</td>
            <td class="right">{{ $fmtMin($tours['planned_minutes']) }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>

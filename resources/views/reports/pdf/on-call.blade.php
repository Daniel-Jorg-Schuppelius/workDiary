<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Notdienst – {{ $from }} bis {{ $to }}</title>
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
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
    .totals td { border-top: 2px solid #333; font-weight: bold; }
</style>
</head>
<body>
@php
    $fmt = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $pct = fn (float $v) => number_format($v * 100, 1, ',', '.') . ' %';
@endphp

<h1>{{ __('Notdienst-Auswertung') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene Bereitschaft' }} ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Mitarbeiter</div><div class="value">{{ $totals['users'] }}</div></td>
        <td><div class="label">Bereitschaft</div><div class="value">{{ $fmt($totals['shift_minutes']) }}</div></td>
        <td><div class="label">Schichten</div><div class="value">{{ $totals['shift_count'] }}</div></td>
        <td><div class="label">{{ __('Einsätze') }}</div><div class="value">{{ $totals['assignment_count'] }} · {{ $fmt($totals['assignment_minutes']) }}</div></td>
        <td><div class="label">{{ __('Aktiv-Anteil') }}</div><div class="value">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            <th class="right">Schichten</th>
            <th class="right">Bereitschaft</th>
            <th class="right">{{ __('Einsätze') }}</th>
            <th class="right">{{ __('Einsatzzeit') }}</th>
            <th class="right">{{ __('Aktiv-Anteil') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r['user']->name }}</td>
                <td class="right">{{ $r['shift_count'] }}</td>
                <td class="right">{{ $fmt($r['shift_minutes']) }}</td>
                <td class="right">{{ $r['assignment_count'] }}</td>
                <td class="right">{{ $fmt($r['assignment_minutes']) }}</td>
                <td class="right">{{ $r['ratio'] !== null ? $pct($r['ratio']) : '–' }}</td>
            </tr>
        @endforeach
        <tr class="totals">
            <td>Gesamt</td>
            <td class="right">{{ $totals['shift_count'] }}</td>
            <td class="right">{{ $fmt($totals['shift_minutes']) }}</td>
            <td class="right">{{ $totals['assignment_count'] }}</td>
            <td class="right">{{ $fmt($totals['assignment_minutes']) }}</td>
            <td class="right">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>

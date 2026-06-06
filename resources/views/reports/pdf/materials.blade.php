<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Materialien – {{ $from }} bis {{ $to }}</title>
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
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; }
</style>
</head>
<body>
@php
    $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
    $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
@endphp

<h1>Materialverbrauch</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Materialien</div><div class="value">{{ $totals['materials'] }}</div></td>
        <td><div class="label">Verwendungen</div><div class="value">{{ $totals['usage_count'] }}</div></td>
        <td><div class="label">Netto Σ</div><div class="value">{{ $eur($totals['line_total_net']) }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Material</th>
            <th>Einheit</th>
            <th class="right">Menge</th>
            <th class="right">Verw.</th>
            <th class="right">Netto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $r)
            <tr>
                <td class="mono">{{ $r['sku'] ?? '—' }}</td>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['unit'] }}</td>
                <td class="right">{{ $num($r['quantity'], 3) }}</td>
                <td class="right">{{ $r['usage_count'] }}</td>
                <td class="right">{{ $eur($r['line_total_net']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; padding:12pt; color:#888;">{{ __('Keine Daten im Zeitraum.') }}</td></tr>
        @endforelse
        @if (! empty($rows))
            <tr class="totals">
                <td colspan="4">Gesamt</td>
                <td class="right">{{ $totals['usage_count'] }}</td>
                <td class="right">{{ $eur($totals['line_total_net']) }}</td>
            </tr>
        @endif
    </tbody>
</table>

</body>
</html>

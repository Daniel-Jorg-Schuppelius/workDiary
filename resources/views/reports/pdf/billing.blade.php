<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Billing – {{ $from }} bis {{ $to }}</title>
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
    .totals td { border-top: 2px solid #333; font-weight: bold; }
    .grid { width: 100%; }
    .grid td { width: 50%; vertical-align: top; padding-right: 6pt; }
    .warn { color: #b30000; font-weight: bold; }
</style>
</head>
<body>
@php
    $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    $fmtMin = function (int $minutes): string {
        $abs = abs($minutes);
        return intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $totalIssuedPaid = ($status['issued']['total'] ?? 0) + ($status['paid']['total'] ?? 0);
@endphp

<h1>{{ __('Abrechnungs-Auswertung') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">{{ __('Ausgestellt + Bezahlt') }}</div><div class="value">{{ $eur($totalIssuedPaid) }}</div></td>
        <td><div class="label">{{ __('Offene Forderungen') }}</div><div class="value">{{ $eur($aging['open_total']) }}</div></td>
        <td><div class="label">&gt; 30 Tage</div><div class="value {{ $aging['buckets']['30_plus']['count'] > 0 ? 'warn' : '' }}">{{ $aging['buckets']['30_plus']['count'] }} ({{ $eur($aging['buckets']['30_plus']['total']) }})</div></td>
        <td><div class="label">{{ __('Unbillte Zeit') }}</div><div class="value">{{ $fmtMin($unbilled['minutes']) }} · {{ $eur($unbilled['projected_revenue']) }}</div></td>
    </tr>
</table>

<table class="grid">
    <tr>
        <td>
            <h2>{{ __('Rechnungen nach Status') }}</h2>
            <table class="data">
                <thead><tr><th>Status</th><th class="right">Anzahl</th><th class="right">Netto</th><th class="right">Brutto</th></tr></thead>
                <tbody>
                    @foreach ($status as $st => $s)
                        <tr>
                            <td>{{ $st }}</td>
                            <td class="right">{{ $s['count'] }}</td>
                            <td class="right">{{ $eur($s['subtotal']) }}</td>
                            <td class="right">{{ $eur($s['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <h2>{{ __('Aging – offene Posten') }}</h2>
            <table class="data">
                <thead><tr><th>Bucket</th><th class="right">Anzahl</th><th class="right">Summe</th></tr></thead>
                <tbody>
                    @foreach ($aging['buckets'] as $k => $b)
                        <tr class="{{ $k === '30_plus' && $b['count'] > 0 ? 'warn' : '' }}">
                            <td>{{ $k }}</td>
                            <td class="right">{{ $b['count'] }}</td>
                            <td class="right">{{ $eur($b['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="totals"><td>Offen gesamt</td><td></td><td class="right">{{ $eur($aging['open_total']) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>Top-Kunden (ausgestellt + bezahlt)</h2>
<table class="data">
    <thead><tr><th>Kunde</th><th class="right">Rechnungen</th><th class="right">Brutto</th></tr></thead>
    <tbody>
        @forelse ($perCustomer as $r)
            <tr>
                <td>{{ $r['customer']->name }}</td>
                <td class="right">{{ $r['count'] }}</td>
                <td class="right">{{ $eur($r['total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align:center; padding:12pt; color:#888;">{{ __('Keine Rechnungen im Zeitraum.') }}</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>

<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Audit-Aktivität – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    h2    { font-size: 10.5pt; margin: 10pt 0 3pt 0; }
    .meta { font-size: 8.5pt; color: #555; margin-bottom: 8pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    .kpis td { padding: 3pt 5pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 7.5pt; color: #666; }
    .kpis .value { font-size: 10.5pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 2pt 4pt; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    .grid { width: 100%; }
    .grid td { width: 33%; vertical-align: top; padding-right: 5pt; }
    .small { font-size: 7.5pt; }
</style>
</head>
<body>
@php
    $shortType = function (?string $fqcn): string {
        if ($fqcn === null || $fqcn === '') return '—';
        $parts = explode('\\', $fqcn);
        return end($parts) ?: $fqcn;
    };
@endphp

<h1>Audit-Aktivität</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Events Σ</div><div class="value">{{ $totals['total'] }}</div></td>
        <td><div class="label">Aktive User</div><div class="value">{{ $totals['users'] }}</div></td>
        <td><div class="label">Entity-Typen</div><div class="value">{{ $totals['types'] }}</div></td>
    </tr>
</table>

<table class="grid">
    <tr>
        <td>
            <h2>Nach Event</h2>
            <table class="data">
                <thead><tr><th>Event</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($byEvent as $ev => $c)
                        <tr><td>{{ $ev }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <h2>Nach Typ</h2>
            <table class="data">
                <thead><tr><th>Typ</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($byType as $t => $c)
                        <tr><td class="small">{{ $shortType($t) }}</td><td class="right">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <h2>Nach User</h2>
            <table class="data">
                <thead><tr><th>User</th><th class="right">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($byUser as $u)
                        <tr><td>{{ $u['user']?->name ?? '—' }}</td><td class="right">{{ $u['count'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>Letzte 100 Events</h2>
<table class="data">
    <thead>
        <tr>
            <th>Zeitpunkt</th>
            <th>User</th>
            <th>Event</th>
            <th>Typ</th>
            <th class="right">ID</th>
            <th>IP</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($recent as $log)
            <tr>
                <td class="small">{{ optional($log->created_at)->format('d.m.Y H:i:s') }}</td>
                <td>{{ $log->user?->name ?? '—' }}</td>
                <td>{{ $log->event }}</td>
                <td class="small">{{ $shortType($log->auditable_type) }}</td>
                <td class="right">{{ $log->auditable_id }}</td>
                <td class="small">{{ $log->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; padding:12pt; color:#888;">Keine Events.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>

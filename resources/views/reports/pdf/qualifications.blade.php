<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Qualifikationsmatrix – {{ now()->format('d.m.Y') }}</title>
<style>
    @page { margin: 12mm 10mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    .meta { font-size: 8pt; color: #555; margin-bottom: 8pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    .kpis td { padding: 3pt 5pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 7.5pt; color: #666; }
    .kpis .value { font-size: 10pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #ccc; padding: 2pt 3pt; vertical-align: middle; }
    table.data th { background: #f3f3f3; font-size: 7.5pt; }
    .name { text-align: left; font-weight: bold; }
    .cell { text-align: center; font-size: 7.5pt; }
    .valid    { background: #e6f4ea; }
    .expiring { background: #fff4cc; font-weight: bold; }
    .expired  { background: #fde2e2; font-weight: bold; color: #b30000; }
    .none     { background: #f5f5f5; color: #999; }
</style>
</head>
<body>
@php
    $cellClass = fn (?array $c) => match ($c['state'] ?? null) {
        'expired'  => 'expired',
        'expiring' => 'expiring',
        'valid'    => 'valid',
        default    => 'none',
    };
    $cellText = function (?array $c): string {
        if ($c === null) {
            return '–';
        }
        if ($c['valid_until'] === null) {
            return '✓';
        }
        return \Carbon\Carbon::parse($c['valid_until'])->format('d.m.y');
    };
@endphp

<h1>Qualifikationsmatrix</h1>
<div class="meta">Stand: {{ now()->format('d.m.Y H:i') }}</div>

<table class="kpis">
    <tr>
        <td><div class="label">Mitarbeiter</div><div class="value">{{ $users->count() }}</div></td>
        <td><div class="label">Qualifikationen</div><div class="value">{{ $qualifications->count() }}</div></td>
        <td><div class="label">Zuweisungen</div><div class="value">{{ $totals['total_assignments'] }}</div></td>
        <td><div class="label">Laufen ab (≤30 T.)</div><div class="value">{{ $totals['expiring'] }}</div></td>
        <td><div class="label">Abgelaufen</div><div class="value">{{ $totals['expired'] }}</div></td>
    </tr>
</table>

@if ($users->isEmpty() || $qualifications->isEmpty())
    <p style="text-align:center; padding:20pt; color:#888;">{{ __('Keine Daten vorhanden.') }}</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th class="name">Mitarbeiter</th>
                @foreach ($qualifications as $q)
                    <th>{{ $q->abbreviation ?? \Illuminate\Support\Str::limit($q->name, 12) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="name">{{ $u->name }}</td>
                    @foreach ($qualifications as $q)
                        @php $cell = $matrix[(int) $u->id][(int) $q->id] ?? null; @endphp
                        <td class="cell {{ $cellClass($cell) }}">{{ $cellText($cell) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>

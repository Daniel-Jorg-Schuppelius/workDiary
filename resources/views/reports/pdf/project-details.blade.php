<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Projekt-Details {{ $project->name }} – {{ $year }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    h2    { font-size: 11pt; margin: 12pt 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 8pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    th, td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; }
    th    { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
    tfoot td { border-top: 2px solid #333; font-weight: bold; }
</style>
</head>
<body>

<h1>Projekt: {{ $project->name }}@if ($project->customer) <span style="font-weight:normal;color:#555;">– {{ $project->customer->name }}</span>@endif</h1>
<div class="meta">Jahr: <strong>{{ $year }}</strong> · Erstellt: {{ now()->format('d.m.Y H:i') }}</div>

<h2>Monatswerte</h2>
<table>
    <thead>
        <tr><th>Monat</th><th class="right">Stunden</th><th class="right">Erlös</th></tr>
    </thead>
    <tbody>
        @foreach ($monthMatrix as $idx => $row)
            @php
                $h = intdiv((int) $row['minutes'], 60);
                $m = (int) $row['minutes'] % 60;
            @endphp
            <tr>
                <td>{{ $monthLabels[$idx] ?? $idx }}</td>
                <td class="right">{{ $row['minutes'] > 0 ? sprintf('%d:%02d', $h, $m) : '–' }}</td>
                <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @php
            $hY = intdiv((int) $yearMinutes, 60);
            $mY = (int) $yearMinutes % 60;
        @endphp
        <tr><td>Gesamt</td><td class="right">{{ $hY }}:{{ str_pad((string) $mY, 2, '0', STR_PAD_LEFT) }}</td><td class="right">{{ number_format((float) $yearRate, 2, ',', '.') }} €</td></tr>
    </tfoot>
</table>

@if (count($byUser) > 0)
    <h2>Aufteilung pro Mitarbeiter</h2>
    <table>
        <thead>
            <tr><th>Mitarbeiter</th><th class="right">Stunden</th><th class="right">Erlös</th></tr>
        </thead>
        <tbody>
            @foreach ($byUser as $uid => $row)
                @php
                    $h = intdiv((int) $row['minutes'], 60);
                    $m = (int) $row['minutes'] % 60;
                @endphp
                <tr>
                    <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                    <td class="right">{{ $h }}:{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>

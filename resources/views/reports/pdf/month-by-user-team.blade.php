<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Team-Monatsreport – {{ $year }}</title>
<style>
    @page { margin: 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 8pt; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; }
    th    { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    tfoot td { border-top: 2px solid #333; font-weight: bold; }
</style>
</head>
<body>

<h1>Team-Monatsreport – {{ $year }}</h1>
<div class="meta">Erstellt: {{ now()->format('d.m.Y H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            @foreach ($monthLabels as $label)
                <th class="right">{{ $label }}</th>
            @endforeach
            <th class="right">Σ Std.</th>
            <th class="right">{{ __('Erlös') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($byUser as $uid => $row)
            @php
                $hT = intdiv((int) $row['total'], 60);
                $mT = (int) $row['total'] % 60;
            @endphp
            <tr>
                <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                @foreach ($row['months'] as $minutes)
                    @php
                        $h = intdiv((int) $minutes, 60);
                        $m = (int) $minutes % 60;
                    @endphp
                    <td class="right">{{ $minutes > 0 ? sprintf('%d:%02d', $h, $m) : '–' }}</td>
                @endforeach
                <td class="right">{{ $hT }}:{{ str_pad((string) $mT, 2, '0', STR_PAD_LEFT) }}</td>
                <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @php
            $hY = intdiv((int) $yearTotal, 60);
            $mY = (int) $yearTotal % 60;
        @endphp
        <tr>
            <td>Σ Monat</td>
            @foreach ($monthTotals as $m)
                @php
                    $h = intdiv((int) $m, 60);
                    $mm = (int) $m % 60;
                @endphp
                <td class="right">{{ $m > 0 ? sprintf('%d:%02d', $h, $mm) : '–' }}</td>
            @endforeach
            <td class="right">{{ $hY }}:{{ str_pad((string) $mY, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="right">{{ number_format((float) $yearRate, 2, ',', '.') }} €</td>
        </tr>
    </tfoot>
</table>
</body>
</html>

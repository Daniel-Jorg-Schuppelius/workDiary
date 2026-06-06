<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Woche – {{ $weekLabel }}</title>
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

<h1>Woche pro Mitarbeiter – {{ $weekLabel }}</h1>
<div class="meta">Erstellt: {{ now()->fdatetime() }}</div>

<table>
    <thead>
        <tr>
            <th>Mitarbeiter</th>
            @foreach ($dayLabels as $label)
                <th class="right">{{ $label }}</th>
            @endforeach
            <th class="right">Σ Stunden</th>
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
                @foreach ($row['days'] as $minutes)
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
            $hW = intdiv((int) $weekTotal, 60);
            $mW = (int) $weekTotal % 60;
        @endphp
        <tr>
            <td>Σ Tag</td>
            @foreach ($dayTotals as $m)
                @php
                    $h = intdiv((int) $m, 60);
                    $mm = (int) $m % 60;
                @endphp
                <td class="right">{{ $m > 0 ? sprintf('%d:%02d', $h, $mm) : '–' }}</td>
            @endforeach
            <td class="right">{{ $hW }}:{{ str_pad((string) $mW, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="right">{{ number_format((float) $weekRate, 2, ',', '.') }} €</td>
        </tr>
    </tfoot>
</table>

</body>
</html>

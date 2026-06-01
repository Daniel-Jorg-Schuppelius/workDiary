<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Kunden &amp; Projekte – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; }
    h1    { font-size: 15pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
    th, td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    th    { background: #f3f3f3; text-align: left; font-size: 9pt; }
    .right { text-align: right; }
    .customer-row td { background: #eef; font-weight: bold; }
    .project-row td { padding-left: 16pt; }
    .totals td { border-top: 2px solid #333; font-weight: bold; padding: 5pt; }
</style>
</head>
<body>

<h1>{{ __('Kunden & Projekte') }}</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Team' : 'Eigene' }} ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('Kunde / Projekt') }}</th>
            <th style="width: 14%">{{ __('Projekt-Nr.') }}</th>
            <th class="right" style="width: 14%">Stunden</th>
            <th class="right" style="width: 16%">{{ __('Erlös') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($bucket as $row)
            @php
                $hC = intdiv((int) $row['minutes'], 60);
                $mC = (int) $row['minutes'] % 60;
                $customerName = $row['customer'] ? $row['customer']->name : '(Ohne Kunde)';
            @endphp
            <tr class="customer-row">
                <td>{{ $customerName }}</td>
                <td></td>
                <td class="right">{{ $hC }}:{{ str_pad((string) $mC, 2, '0', STR_PAD_LEFT) }}</td>
                <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
            </tr>
            @foreach ($row['projects'] as $entry)
                @php
                    $hp = intdiv((int) $entry['minutes'], 60);
                    $mp = (int) $entry['minutes'] % 60;
                @endphp
                <tr class="project-row">
                    <td>{{ $entry['project']->name }}@if ($entry['project']->foreignCustomer) · {{ $entry['project']->foreignCustomer->name }}@endif</td>
                    <td>{{ $entry['project']->number }}</td>
                    <td class="right">{{ $hp }}:{{ str_pad((string) $mp, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="right">{{ number_format((float) $entry['rate'], 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        @endforeach
        @php
            $hT = intdiv((int) $totalMinutes, 60);
            $mT = (int) $totalMinutes % 60;
        @endphp
        <tr class="totals">
            <td>Gesamt</td>
            <td></td>
            <td class="right">{{ $hT }}:{{ str_pad((string) $mT, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="right">{{ number_format((float) $totalRate, 2, ',', '.') }} €</td>
        </tr>
    </tbody>
</table>

</body>
</html>

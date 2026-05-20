<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Mein Monat – {{ $monthLabel }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 15pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
    th, td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    th    { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    .day-header { background: #eef; font-weight: bold; }
    .day-header.sun th { color: #c00; }
    .totals { margin-top: 6pt; }
    .totals td { border: 0; padding: 2pt 5pt; font-size: 9.5pt; }
    .badge { display: inline-block; padding: 1pt 4pt; border-radius: 2pt; font-size: 7.5pt; background: #ddd; }
    .small { font-size: 8pt; color: #666; }
</style>
</head>
<body>

<h1>Mein Monat – {{ $monthLabel }}</h1>
<div class="meta">Erstellt: {{ now()->format('d.m.Y H:i') }} – Nutzer: {{ auth()->user()?->name }}</div>

@forelse ($byDay as $date => $row)
    @php
        $h = intdiv((int) $row['minutes'], 60);
        $m = (int) $row['minutes'] % 60;
        $isSunday = \Carbon\Carbon::parse($date)->isSunday();
    @endphp
    <table>
        <thead>
            <tr class="day-header{{ $isSunday ? ' sun' : '' }}">
                <th colspan="4">{{ \Carbon\Carbon::parse($date)->locale(app()->getLocale())->isoFormat('dddd, DD.MM.YYYY') }}</th>
                <th class="right">{{ $h }}:{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }} h</th>
                <th class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</th>
            </tr>
            <tr>
                <th style="width: 8%">Start</th>
                <th style="width: 8%">Ende</th>
                <th style="width: 10%">Art</th>
                <th>Projekt / Aufgabe / Beschreibung</th>
                <th class="right" style="width: 12%">Dauer</th>
                <th class="right" style="width: 14%">Erlös</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($row['entries'] as $e)
                @php
                    $eh = intdiv((int) $e->minutes, 60);
                    $em = (int) $e->minutes % 60;
                @endphp
                <tr>
                    <td>{{ $e->started_at ? \Carbon\Carbon::parse((string) $e->started_at)->format('H:i') : '' }}</td>
                    <td>{{ $e->ended_at ? \Carbon\Carbon::parse((string) $e->ended_at)->format('H:i') : '' }}</td>
                    <td><span class="badge">{{ $e->kind?->label() ?? '' }}</span></td>
                    <td>
                        @if ($e->project)
                            <strong>{{ $e->project->name }}</strong>@if ($e->project->customer) <span class="small">– {{ $e->project->customer->name }}</span>@endif<br>
                        @endif
                        @if ($e->task)<span class="small">{{ $e->task->title }}</span><br>@endif
                        {{ $e->description }}
                    </td>
                    <td class="right">{{ $eh }}:{{ str_pad((string) $em, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="right">{{ number_format((float) $e->rate, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p>Keine Einträge im gewählten Monat.</p>
@endforelse

@php
    $hM = intdiv((int) $monthMinutes, 60);
    $mM = (int) $monthMinutes % 60;
@endphp
<table class="totals">
    <tr>
        <td class="right" style="width: 70%;"><strong>Monat gesamt:</strong></td>
        <td class="right" style="width: 15%;"><strong>{{ $hM }}:{{ str_pad((string) $mM, 2, '0', STR_PAD_LEFT) }} h</strong></td>
        <td class="right" style="width: 15%;"><strong>{{ number_format((float) $monthRate, 2, ',', '.') }} €</strong></td>
    </tr>
</table>

</body>
</html>

{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Helpdesk-Bericht als PDF (Feature 065, MVP-159): Kennzahlen-Tabellen
     mit Exportkopf (Reportcode, metric_version, Berechnungsstand,
     Zeitraum) — Muster 064 (agile/reports/pdf). --}}

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        h2 { font-size: 13px; margin: 14px 0 4px; }
        .meta { color: #555; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #bbb; padding: 3px 6px; text-align: left; }
        th { background: #eee; }
        td.num, th.num { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ __('Helpdesk-Bericht') }}</h1>
    <p class="meta">
        {{ __('Reportcode:') }} helpdesk_report_v{{ $metricVersion }} ·
        {{ __('Berechnungsstand:') }} {{ now()->isoFormat('L LT') }} ·
        {{ __('Zeitraum:') }} {{ $from->isoFormat('L') }} – {{ $to->isoFormat('L') }}
    </p>

    <h2>{{ __('SLA-Erfüllung') }} <small>({{ __('Prozent') }})</small></h2>
    <table>
        <thead><tr><th class="num">{{ __('Reaktion') }}</th><th class="num">{{ __('Lösung') }}</th><th class="num">{{ __('Tickets im Zeitraum') }}</th></tr></thead>
        <tbody>
            <tr><td class="num">{{ $compliance['reaction_met'] }}</td><td class="num">{{ $compliance['resolution_met'] }}</td><td class="num">{{ $compliance['total'] }}</td></tr>
        </tbody>
    </table>

    <h2>{{ __('Reaktions-/Lösungszeiten') }} <small>({{ __('Stunden, Pausen abgezogen') }})</small></h2>
    <table>
        <thead><tr><th></th><th class="num">P50</th><th class="num">P85</th><th class="num">P95</th><th class="num">n</th></tr></thead>
        <tbody>
            <tr><td>{{ __('Reaktion') }}</td><td class="num">{{ $times['reaction']['p50'] }}</td><td class="num">{{ $times['reaction']['p85'] }}</td><td class="num">{{ $times['reaction']['p95'] }}</td><td class="num">{{ $times['reaction']['count'] }}</td></tr>
            <tr><td>{{ __('Lösung') }}</td><td class="num">{{ $times['resolution']['p50'] }}</td><td class="num">{{ $times['resolution']['p85'] }}</td><td class="num">{{ $times['resolution']['p95'] }}</td><td class="num">{{ $times['resolution']['count'] }}</td></tr>
        </tbody>
    </table>

    <h2>{{ __('Erstlösungsquote (FCR) je Queue') }} <small>({{ __('gelöste Tickets im Zeitraum') }})</small></h2>
    <table>
        <thead><tr><th>{{ __('Queue') }}</th><th class="num">{{ __('Gelöst') }}</th><th class="num">FCR</th><th class="num">{{ __('FCR-Quote') }} %</th><th class="num">{{ __('Wiedereröffnet') }}</th><th class="num">{{ __('Weitergeleitet') }}</th></tr></thead>
        <tbody>
            @forelse ($fcr['queues'] as $queue => $row)
                <tr><td>{{ $queue }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['fcr'] }}</td><td class="num">{{ $row['fcr_rate'] }}</td><td class="num">{{ $row['reopened'] }}</td><td class="num">{{ $row['requeued'] }}</td></tr>
            @empty
                <tr><td colspan="6">{{ __('Keine gelösten Tickets im Zeitraum.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="meta">{{ __('Gesamt:') }} {{ $fcr['fcr_rate'] }} % FCR · {{ $fcr['reopened_rate'] }} % {{ __('Wiederöffnungsquote') }} · {{ $fcr['requeued_rate'] }} % {{ __('Weiterleitungsquote') }} ({{ $fcr['total'] }} {{ __('Tickets') }})</p>

    <h2>{{ __('Aging offener Tickets') }} <small>({{ __('Altersbänder in Tagen, Stichtag heute') }})</small></h2>
    <table>
        <thead><tr><th>{{ __('Alter in Tagen') }}</th><th class="num">{{ __('offene Tickets') }}</th><th>{{ __('Queues') }}</th></tr></thead>
        <tbody>
            @foreach ($aging as $band => $row)
                <tr>
                    <td>{{ $band }}</td>
                    <td class="num">{{ $row['total'] }}</td>
                    <td>{{ collect($row['queues'])->map(fn($count, $queue) => $queue . ': ' . $count)->implode(' · ') ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Zufriedenheit') }} <small>({{ __('Portal-Bewertungen 1–5') }})</small></h2>
    <table>
        <thead><tr><th class="num">{{ __('Score') }}</th><th class="num">{{ __('Bewertungen') }}</th></tr></thead>
        <tbody>
            @foreach ($satisfaction['distribution'] as $score => $count)
                <tr><td class="num">{{ $score }}</td><td class="num">{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p class="meta">{{ __('Ø:') }} {{ $satisfaction['average'] }} · {{ __('Rücklaufquote:') }} {{ $satisfaction['response_rate'] }} % ({{ $satisfaction['responses'] }} / {{ $satisfaction['closed_total'] }})</p>
</body>
</html>

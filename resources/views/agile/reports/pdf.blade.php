{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Sprint-Cockpit als PDF (Feature 064, P11): Kennzahlen-Tabellen mit
     Exportkopf (Reportcode, metric_version, Berechnungsstand, Einheiten). --}}

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
    <h1>{{ __('Sprint-Cockpit') }} — {{ $project->name }}</h1>
    <p class="meta">
        {{ __('Reportcode:') }} agile_sprint_cockpit_v{{ $velocity->metricVersion }} ·
        {{ __('Berechnungsstand:') }} {{ $velocity->computedAt->isoFormat('L LT') }} ·
        {{ __('Board:') }} {{ $board->name }} ({{ $board->method }})
        @if ($sprint) · {{ __('Sprint:') }} {{ $sprint->name }} @endif
    </p>

    @if ($burndown !== null)
        <h2>{{ __('Burndown') }} <small>({{ __('Story Points') }})</small></h2>
        <table>
            <thead><tr><th>{{ __('Datum') }}</th><th class="num">{{ __('Verbleibend') }}</th><th class="num">{{ __('Scope-Delta') }}</th></tr></thead>
            <tbody>
                @foreach ($burndown->data['series'] as $row)
                    <tr><td>{{ $row['date'] }}</td><td class="num">{{ $row['remaining'] }}</td><td class="num">{{ $row['scope_delta'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('Velocity je Sprint') }} <small>({{ __('Story Points') }})</small></h2>
    <table>
        <thead><tr><th>{{ __('Sprint') }}</th><th class="num">{{ __('Erledigt') }}</th><th class="num">{{ __('Zugesagt') }}</th><th class="num">{{ __('Scope-Zugänge') }}</th></tr></thead>
        <tbody>
            @forelse ($velocity->data['sprints'] as $row)
                <tr><td>{{ $row['sprint'] }}</td><td class="num">{{ $row['done_points'] }}</td><td class="num">{{ $row['committed_points'] }}</td><td class="num">{{ $row['scope_added'] }}</td></tr>
            @empty
                <tr><td colspan="4">{{ __('Noch keine abgeschlossenen Sprints.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="meta">{{ __('Median:') }} {{ $velocity->data['median'] }} · {{ __('Spannweite:') }} {{ $velocity->data['min'] }}–{{ $velocity->data['max'] }}</p>

    <h2>{{ __('Qualitätsreihe') }} <small>({{ __('Ereignisse je Woche') }})</small></h2>
    <table>
        <thead><tr><th>{{ __('Woche') }}</th><th class="num">{{ __('Wiederöffnungen') }}</th><th class="num">{{ __('Übersteuerungen') }}</th></tr></thead>
        <tbody>
            @forelse ($quality->data['weeks'] as $week => $row)
                <tr><td>{{ $week }}</td><td class="num">{{ $row['reopened'] }}</td><td class="num">{{ $row['overrides'] }}</td></tr>
            @empty
                <tr><td colspan="3">{{ __('Keine Qualitätsereignisse.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

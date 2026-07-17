{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Sprint-Cockpit als PDF (Feature 064, P11): Kennzahlen-Tabellen mit
     Exportkopf (Reportcode, metric_version, Berechnungsstand, Einheiten);
     Branding/Grundgerüst via reports.pdf.layout (D3). --}}

@extends('reports.pdf.layout')

@section('pdf-title', __('Sprint-Cockpit') . ' — ' . $project->name)
@section('pdf-heading', __('Sprint-Cockpit') . ' — ' . $project->name)

@push('pdf-styles')
<style>
    table { margin-bottom: 8px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Reportcode:') }} agile_sprint_cockpit_v{{ $velocity->metricVersion }} ·
    {{ __('Berechnungsstand:') }} {{ $velocity->computedAt->isoFormat('L LT') }} ·
    {{ __('Board:') }} {{ $board->name }} ({{ $board->method }})
    @if ($sprint) · {{ __('Sprint:') }} {{ $sprint->name }} @endif
@endsection

@section('pdf-table')
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
@endsection

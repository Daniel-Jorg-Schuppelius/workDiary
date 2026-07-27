@extends('reports.pdf.layout')

@section('pdf-title', __('Audit-Aktivität') . ' – ' . $from . ' ' . __('bis') . ' ' . $to)
@section('pdf-heading', __('Audit-Aktivität'))

@push('pdf-styles')
<style>
    .grid { width: 100%; }
    .grid > tr > td, .grid td.col { border: 0; width: 33%; vertical-align: top; padding: 0 5px 0 0; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $shortType = function (?string $fqcn): string {
            if ($fqcn === null || $fqcn === '') return '—';
            $parts = explode('\\', $fqcn);
            $short = end($parts) ?: $fqcn;
            $key = 'entity-types.' . $short;
            return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $short;
        };
        /** Übersetzte Audit-Event-Bezeichnungen (mit Fallback auf den Roh-Key). */
        $eventLabel = function (string $event): string {
            $key = 'audit-events.' . $event;
            return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $event;
        };
        /** Lokalisiert kanonische Seed-Namen (z. B. „Administrator“); freie Namen bleiben. */
        $userLabel = function (?string $name): string {
            if ($name === null || $name === '') return '—';
            $key = 'well-known-names.' . $name;
            return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $name;
        };
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Events Σ') }}</div><div class="value">{{ $totals['total'] }}</div></td>
            <td><div class="label">{{ __('Aktive User') }}</div><div class="value">{{ $totals['users'] }}</div></td>
            <td><div class="label">{{ __('Entity-Typen') }}</div><div class="value">{{ $totals['types'] }}</div></td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="col">
                <h2>{{ __('Nach Event') }}</h2>
                <table class="data">
                    <thead><tr><th>{{ __('Event') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($byEvent as $ev => $c)
                            <tr><td>{{ $eventLabel($ev) }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="col">
                <h2>{{ __('Nach Typ') }}</h2>
                <table class="data">
                    <thead><tr><th>{{ __('Typ') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($byType as $t => $c)
                            <tr><td class="small">{{ $shortType($t) }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="col">
                <h2>{{ __('Nach Benutzer') }}</h2>
                <table class="data">
                    <thead><tr><th>{{ __('Benutzer') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($byUser as $u)
                            <tr><td>{{ $userLabel($u['user']?->name) }}</td><td class="right">{{ $u['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ __('Letzte 100 Events') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Zeitpunkt') }}</th>
                <th>{{ __('Benutzer') }}</th>
                <th>{{ __('Event') }}</th>
                <th>{{ __('Typ') }}</th>
                <th class="right">{{ __('ID') }}</th>
                <th>{{ __('IP') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recent as $log)
                <tr>
                    <td class="small">{{ $log->created_at?->orgTz()->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $userLabel($log->user?->name) }}</td>
                    <td>{{ $eventLabel($log->event) }}</td>
                    <td class="small">{{ $shortType($log->auditable_type) }}</td>
                    <td class="right">{{ $log->auditable_id }}</td>
                    <td class="small">{{ $log->ip?->getValue() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; padding:12px; color:#888;">{{ __('Keine Events.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

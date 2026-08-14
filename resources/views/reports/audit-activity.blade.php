{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : audit-activity.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Audit-Aktivität'))
@section('nav-title', __('Audit-Aktivität'))

@section('content')
@php
    /** Übersetzte Audit-Event-Bezeichnungen (mit Fallback auf den Roh-Key). */
    $eventLabel = function (string $event): string {
        $key = 'audit-events.' . $event;
        return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $event;
    };
    $shortType = function (?string $fqcn): string {
        if ($fqcn === null || $fqcn === '') return '—';
        $parts = explode('\\', $fqcn);
        $short = end($parts) ?: $fqcn;
        $key = 'entity-types.' . $short;
        return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $short;
    };
    /** Lokalisiert kanonische Seed-Namen (z. B. „Administrator“); freie Namen bleiben. */
    $userLabel = function (?string $name): string {
        if ($name === null || $name === '') return '—';
        $key = 'well-known-names.' . $name;
        return \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : $name;
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Audit-Events nach Event-Typ, Entity und Nutzer im Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.audit-activity', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.audit-activity', array_merge($standardFilters->toQueryParams(), ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.audit-activity', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.audit-activity')" :reset="route('reports.audit-activity')">
        @include('reports._standard_filters', ['idPrefix' => 'audit'])
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Events Σ')" :value="$totals['total']" />
        <x-kpi-tile :label="__('Aktive Benutzer')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Entity-Typen')" :value="$totals['types']" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('Ereignisse im Verlauf')" :unit="__('Events')"
                       :series="$timelineSeries" :x-label="__('Zeitraum')" :y-label="__('Events')" />
        <x-charts.bar-h :title="__('Top-Akteure (Top 15)')" :unit="__('Events')"
                        :series="$topActorsSeries" :x-label="__('Benutzer')" :y-label="__('Events')" />
    </div>

    <x-charts.stacked-bar :title="__('Ereignisse :per nach Typ', ['per' => $periodPhrase])" :unit="__('Events')"
                          :series="$monthlyEventSeries" :bands="$eventBands" :x-label="$periodAxis" />

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Event') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Event') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($byEvent as $ev => $c)
                    <tr><td>{{ $eventLabel($ev) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="2" :title="__('Keine Daten')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Entity-Typ (Top 20)') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($byType as $t => $c)
                    <tr><td class="text-xs">{{ $shortType($t) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="2" :title="__('Keine Daten')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Benutzer (Top 20)') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Benutzer') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($byUser as $u)
                    <tr><td>{{ $userLabel($u['user']?->name) }}</td><td class="text-right tabular-nums">{{ $u['count'] }}</td></tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="2" :title="__('Keine Daten')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Letzte 100 Events') }}</h3>
        @if ($recent->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :title="__('Keine Events im Zeitraum.')" />
        @else
            <x-table size="xs" table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date">{{ __('Zeitpunkt') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Benutzer') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Event') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('ID') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('IP') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($recent as $log)
                    <tr>
                        <td class="tabular-nums" data-sort-value="{{ $log->created_at?->orgTz()->format('Y-m-d H:i:s') }}">{{ $log->created_at?->orgTz()->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $userLabel($log->user?->name) }}</td>
                        <td>{{ $eventLabel($log->event) }}</td>
                        <td class="text-xs">{{ $shortType($log->auditable_type) }}</td>
                        <td class="tabular-nums">{{ $log->auditable_id }}</td>
                        <td class="text-xs text-base-content/60">{{ $log->ip?->getValue() }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

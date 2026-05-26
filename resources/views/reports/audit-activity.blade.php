@extends('layouts.app')
@section('title', __('Audit-Aktivität'))
@section('nav-title', __('Audit-Aktivität'))

@section('content')
@php
    $eventLabels = [
        'created'    => __('Angelegt'),
        'updated'    => __('Geändert'),
        'deleted'    => __('Gelöscht'),
        'archived'   => __('Archiviert'),
        'restored'   => __('Wiederhergestellt'),
    ];
    $shortType = function (?string $fqcn): string {
        if ($fqcn === null || $fqcn === '') return '—';
        $parts = explode('\\', $fqcn);
        return end($parts) ?: $fqcn;
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Audit-Events nach Event-Typ, Entity und Nutzer im Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.audit-activity', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.audit-activity', ['export' => 'pdf'])"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Events Σ') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['total'] }}</div></div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktive User') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['users'] }}</div></div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Entity-Typen') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['types'] }}</div></div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Event') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Event') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($byEvent as $ev => $c)
                    <tr><td>{{ $eventLabels[$ev] ?? $ev }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="2" :title="__('Keine Daten')" compact />
                @endforelse
            </x-table>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach User (Top 20)') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('User') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($byUser as $u)
                    <tr><td>{{ $u['user']?->name ?? '—' }}</td><td class="text-right tabular-nums">{{ $u['count'] }}</td></tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="2" :title="__('Keine Daten')" compact />
                @endforelse
            </x-table>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Letzte 100 Events') }}</h3>
        @if ($recent->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :title="__('Keine Events im Zeitraum.')" />
        @else
            <x-table size="xs" table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date">{{ __('Zeitpunkt') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('User') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Event') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('ID') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('IP') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($recent as $log)
                    <tr>
                        <td class="tabular-nums" data-sort-value="{{ optional($log->created_at)->format('Y-m-d H:i:s') }}">{{ optional($log->created_at)->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td>{{ $eventLabels[$log->event] ?? $log->event }}</td>
                        <td class="text-xs">{{ $shortType($log->auditable_type) }}</td>
                        <td class="tabular-nums">{{ $log->auditable_id }}</td>
                        <td class="text-xs text-base-content/60">{{ $log->ip }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection

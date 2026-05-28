@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('nav-title', __('Qualifikationsmatrix'))

@section('content')
@php
    $cellClass = fn (?array $c) => match ($c['state'] ?? null) {
        'expired'  => 'bg-error/20 text-error font-bold',
        'expiring' => 'bg-warning/20 text-warning-content font-semibold',
        'valid'    => 'bg-success/15 text-success-content',
        default    => 'bg-base-200 text-base-content/40',
    };
    $cellText = function (?array $c): string {
        if ($c === null) {
            return '–';
        }
        if ($c['valid_until'] === null) {
            return '✓';
        }
        return \Carbon\Carbon::parse($c['valid_until'])->format('d.m.Y');
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Qualifikationsmatrix der Mitarbeiter inkl. Ablauf- und Warnstatus.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.qualifications', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.qualifications', ['export' => 'pdf'])"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Mitarbeiter')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Qualifikationen')" :value="$totals['qualifications']" />
        <x-kpi-tile :label="__('Zuweisungen')" :value="$totals['assignments']" />
        <x-kpi-tile :label="__('Laufen ab (≤30 T.)')" :value="$totals['expiring']" :tone="$totals['expiring'] > 0 ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('Abgelaufen')" :value="$totals['expired']" :tone="$totals['expired'] > 0 ? 'error' : 'neutral'" />
    </div>

    <x-card>
        @if ($users->isEmpty() || $qualifications->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>' :title="__('Keine Qualifikations-Zuweisungen vorhanden.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th class="sticky left-0 bg-base-100">{{ __('Mitarbeiter') }}</th>
                        @foreach ($qualifications as $q)
                            <th class="text-center align-bottom" title="{{ $q->name }}">
                                <span class="block text-xs font-semibold">{{ $q->abbreviation ?? $q->name }}</span>
                            </th>
                        @endforeach
                    </tr>
                </x-slot:head>
                @foreach ($users as $u)
                    <tr>
                        <td class="sticky left-0 bg-base-100 font-semibold">{{ $u->name }}</td>
                        @foreach ($qualifications as $q)
                            @php $cell = $matrix[(int) $u->id][(int) $q->id] ?? null; @endphp
                            <td class="text-center text-xs tabular-nums {{ $cellClass($cell) }}">{{ $cellText($cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-3 flex flex-wrap gap-3 text-xs text-base-content/70">
                <span class="badge bg-success/15">{{ __('gültig') }}</span>
                <span class="badge bg-warning/20">{{ __('läuft in 30 Tagen ab') }}</span>
                <span class="badge bg-error/20">{{ __('abgelaufen') }}</span>
                <span class="badge bg-base-200">{{ __('keine Zuweisung') }}</span>
            </div>
        @endif
    </x-card>
</x-page-shell>
@endsection

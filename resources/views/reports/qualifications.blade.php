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

    <x-filter-bar :action="route('reports.qualifications')" :reset="route('reports.qualifications')">
        <x-slot:extra>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.qualifications', ['export' => 'csv'])"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.qualifications', ['export' => 'pdf'])"
                        show-label>PDF</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['users'] }}</div></div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Qualifikationen') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['qualifications'] }}</div></div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs"><div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Zuweisungen') }}</div><div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['assignments'] }}</div></div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Laufen ab (≤30 T.)') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold {{ $totals['expiring'] > 0 ? 'text-warning' : '' }}">{{ $totals['expiring'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Abgelaufen') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold {{ $totals['expired'] > 0 ? 'text-error' : '' }}">{{ $totals['expired'] }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
    </div>
</x-page-shell>
@endsection

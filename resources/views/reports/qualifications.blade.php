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

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.qualifications')" :reset="route('reports.qualifications')">
        <x-slot:extra>
            <a href="{{ route('reports.qualifications', ['export' => 'csv']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.qualifications', ['export' => 'pdf']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat"><div class="stat-title">{{ __('Mitarbeiter') }}</div><div class="stat-value text-2xl">{{ $totals['users'] }}</div></div>
        <div class="stat"><div class="stat-title">{{ __('Qualifikationen') }}</div><div class="stat-value text-2xl">{{ $totals['qualifications'] }}</div></div>
        <div class="stat"><div class="stat-title">{{ __('Zuweisungen') }}</div><div class="stat-value text-2xl">{{ $totals['assignments'] }}</div></div>
        <div class="stat">
            <div class="stat-title">{{ __('Laufen ab (≤30 T.)') }}</div>
            <div class="stat-value text-2xl {{ $totals['expiring'] > 0 ? 'text-warning' : '' }}">{{ $totals['expiring'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Abgelaufen') }}</div>
            <div class="stat-value text-2xl {{ $totals['expired'] > 0 ? 'text-error' : '' }}">{{ $totals['expired'] }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if ($users->isEmpty() || $qualifications->isEmpty())
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Qualifikations-Zuweisungen vorhanden.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-base-100">{{ __('Mitarbeiter') }}</th>
                            @foreach ($qualifications as $q)
                                <th class="text-center align-bottom" title="{{ $q->name }}">
                                    <span class="block text-xs font-semibold">{{ $q->abbreviation ?? $q->name }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr>
                                <td class="sticky left-0 bg-base-100 font-semibold">{{ $u->name }}</td>
                                @foreach ($qualifications as $q)
                                    @php $cell = $matrix[(int) $u->id][(int) $q->id] ?? null; @endphp
                                    <td class="text-center text-xs tabular-nums {{ $cellClass($cell) }}">{{ $cellText($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-xs text-base-content/70">
                <span class="badge bg-success/15">{{ __('gültig') }}</span>
                <span class="badge bg-warning/20">{{ __('läuft in 30 Tagen ab') }}</span>
                <span class="badge bg-error/20">{{ __('abgelaufen') }}</span>
                <span class="badge bg-base-200">{{ __('keine Zuweisung') }}</span>
            </div>
        @endif
    </div>
</div>
@endsection

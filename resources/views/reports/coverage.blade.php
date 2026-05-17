@extends('layouts.app')
@section('title', __('Coverage / Soll-Ist-Besetzung'))
@section('nav-title', __('Coverage'))

@section('content')
@php
    $pct = fn (float $v) => number_format($v * 100, 1, ',', '.') . ' %';
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.coverage')" :reset="route('reports.coverage')">
        <x-slot:extra>
            <a href="{{ route('reports.coverage', ['export' => 'csv']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.coverage', ['export' => 'pdf']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Schichttypen') }}</div>
            <div class="stat-value text-2xl">{{ $totals['shift_types'] }}</div>
            <div class="stat-desc">{{ $daySpan }} {{ __('Tage') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Soll (Personentage)') }}</div>
            <div class="stat-value text-2xl">{{ $totals['required'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Ist (Personentage)') }}</div>
            <div class="stat-value text-2xl">{{ $totals['scheduled'] }}</div>
            <div class="stat-desc {{ $totals['gap'] < 0 ? 'text-error' : ($totals['gap'] > 0 ? 'text-success' : '') }}">
                {{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Erfüllung') }}</div>
            <div class="stat-value text-2xl">
                {{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Tage mit Unterdeckung') }}</div>
            <div class="stat-value text-2xl {{ $totals['days_under'] > 0 ? 'text-error' : '' }}">
                {{ $totals['days_under'] }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Pro Schichttyp') }}</h3>
        @if (empty($rows))
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Soll-Vorgaben oder Plan-Einträge im gewählten Zeitraum.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Schichttyp') }}</th>
                            <th class="text-right">{{ __('Soll') }}</th>
                            <th class="text-right">{{ __('Ist') }}</th>
                            <th class="text-right">{{ __('Differenz') }}</th>
                            <th class="text-right">{{ __('Erfüllung') }}</th>
                            <th class="text-right">{{ __('Tage unter') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-semibold">
                                    @if ($r['shiftType']->color)
                                        <span class="mr-2 inline-block size-2 rounded-full align-middle" style="background-color: {{ $r['shiftType']->color }};"></span>
                                    @endif
                                    {{ $r['shiftType']->name }}
                                    @if ($r['shiftType']->abbreviation)
                                        <span class="ml-1 text-xs text-base-content/50">{{ $r['shiftType']->abbreviation }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $r['required'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['scheduled'] }}</td>
                                <td class="text-right tabular-nums {{ $r['gap'] < 0 ? 'text-error font-semibold' : ($r['gap'] > 0 ? 'text-success' : '') }}">
                                    {{ $r['gap'] > 0 ? '+' : '' }}{{ $r['gap'] }}
                                </td>
                                <td class="text-right tabular-nums">{{ $r['fill_rate'] !== null ? $pct($r['fill_rate']) : '–' }}</td>
                                <td class="text-right tabular-nums {{ $r['days_under'] > 0 ? 'text-error' : '' }}">{{ $r['days_under'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $totals['required'] }}</td>
                            <td class="text-right tabular-nums">{{ $totals['scheduled'] }}</td>
                            <td class="text-right tabular-nums {{ $totals['gap'] < 0 ? 'text-error' : '' }}">
                                {{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}
                            </td>
                            <td class="text-right tabular-nums">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</td>
                            <td class="text-right tabular-nums">{{ $totals['days_under'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    @if (! empty($underfilled))
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-error/80">
                {{ __('Tage mit Unterdeckung') }}
                <span class="text-base-content/50">({{ count($underfilled) }})</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="table table-zebra table-xs">
                    <thead>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Schichttyp') }}</th>
                            <th class="text-right">{{ __('Soll') }}</th>
                            <th class="text-right">{{ __('Ist') }}</th>
                            <th class="text-right">{{ __('Lücke') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($underfilled as $u)
                            <tr>
                                <td class="tabular-nums">{{ \Carbon\Carbon::parse($u['date'])->translatedFormat('D, d.m.Y') }}</td>
                                <td>{{ $u['shiftType']->name }}</td>
                                <td class="text-right tabular-nums">{{ $u['required'] }}</td>
                                <td class="text-right tabular-nums">{{ $u['scheduled'] }}</td>
                                <td class="text-right tabular-nums text-error font-semibold">{{ $u['gap'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@extends('layouts.app')
@section('title', __('Auswertungen'))
@section('nav-title', __('Auswertungen'))

@section('content')
@php
    /** @var int $totalMinutes */
    /** @var int $bookedDays */
    /** @var int $activeProjects */
    /** @var int $avgMinutesPerDay */
    $fmtHours = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Kennzahlen im gewählten Zeitraum und Einstieg in alle Auswertungen.')">
            <x-slot:actions>
                <x-help-button topic="reports.overview" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Meine Stunden')" :value="$fmtHours($totalMinutes)" tone="primary" :hint="__('Im gewählten Zeitraum')" />
        <x-kpi-tile :label="__('Erfasste Tage')" :value="$bookedDays" tone="info" />
        <x-kpi-tile :label="__('Ø pro Tag')" :value="$fmtHours($avgMinutesPerDay)" tone="neutral" :hint="__('Bezogen auf erfasste Tage')" />
        <x-kpi-tile :label="__('Aktive Projekte')" :value="$activeProjects" tone="secondary" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="$hoursSeriesLabel" unit="h" :series="$hoursSeries" :x-label="__('Zeitraum')" :y-label="__('Stunden')" />
        <x-charts.bar-h :title="__('Top-Projekte nach Stunden')" unit="h" :series="$topProjects" :x-label="__('Projekt')" :y-label="__('Stunden')" />
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($groups as $group)
            @php($items = array_values(array_filter((array) ($group['items'] ?? []), 'is_array')))
            @if ($items !== [])
                <x-card :title="$group['label']" :icon="$group['icon'] ?? null">
                    <ul class="menu w-full p-0">
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base text-base-content/60" aria-hidden="true">{{ $item['icon'] ?? 'insights' }}</span>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endforeach
    </div>
</x-page-shell>
@endsection

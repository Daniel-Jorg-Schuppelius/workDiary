{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : my-year.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Mein Jahr') . ' ' . $year)
@section('nav-title', __('Mein Jahr') . ' ' . $year)

@section('content')
@php
    /** @var int $year */
    /** @var int $maxCell */
    $maxCell = (int) ($maxCell ?? 0);
    /** @var int $yearTotal */
    /** @var string $kind */
    /** @var array<int, array<int, int>> $matrix */
    /** @var array<int, int> $monthTotals */
    /** @var array<int, int> $daysInMonth */
    /** @var array<int, string> $monthNames */
    $fmt = function (int|float $min): string {
        $min = (int) round($min);
        if ($min <= 0) {
            return '';
        }
        return \App\Support\Formats::duration($min, 'clock', withUnit: false);
    };
    $fmtTotal = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');

    $heatmapRows = [];
    for ($m = 1; $m <= 12; $m++) {
        $cells = [];
        for ($d = 1; $d <= 31; $d++) {
            if ($d > $daysInMonth[$m]) {
                $cells[] = null;
                continue;
            }
            $val = $matrix[$m][$d];
            $cells[] = [
                'value' => $val,
                'title' => sprintf('%02d.%02d.%d', $d, $m, $year) . ' — ' . ($val > 0 ? $fmtTotal($val) : __('keine Einträge')),
                'class' => \Carbon\Carbon::create($year, $m, $d)->isSunday() ? 'text-error' : '',
            ];
        }
        $heatmapRows[] = ['label' => $monthNames[$m], 'url' => $monthUrls[$m] ?? null, 'cells' => $cells];
    }
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Stunden pro Tag und Monat — Färbung skaliert mit dem höchsten Tageswert des Jahres.')" />
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.my-year')" :reset="route('reports.my-year')">
        @include('reports._standard_filters', ['idPrefix' => 'my-year'])
        <x-filter-field :label="__('Art')" for="rep-kind">
            <select id="rep-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($kind === 'all')>{{ __('Alle') }}</option>
                <option value="work" @selected($kind === 'work')>{{ __('Arbeit') }}</option>
                <option value="travel" @selected($kind === 'travel')>{{ __('Reise') }}</option>
                <option value="standby" @selected($kind === 'standby')>{{ __('Bereitschaft') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Jahressumme')" :value="$fmtTotal($yearTotal)" :tone="$yearTotal > 0 ? 'primary' : 'neutral'" />
    </div>

    <x-charts.heatmap
        :title="__('Stunden pro Tag')"
        unit="h"
        :rows="$heatmapRows"
        :col-labels="range(1, 31)"
        :max="$maxCell"
        :x-label="__('Monat')"
        :format="$fmt"
    />

    <x-charts.bar :title="__('Stunden pro Monat')" unit="h" :series="$monthlySeries" :x-label="__('Monat')" :y-label="__('Stunden')" />
</x-page-shell>
@endsection

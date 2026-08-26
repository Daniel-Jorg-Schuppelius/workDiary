{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : expenses.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Spesen-Report'))
@section('nav-title', __('Spesen-Report'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Spesen je Mitarbeiter und Kategorie über den Zeitraum.')" />
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.expenses')" :reset="route('reports.expenses')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamte Organisation') }}</option>
                </select>
            </x-filter-field>
        @endif
        @include('reports._standard_filters', ['idPrefix' => 'expenses', 'statusOptions' => $statusOptions, 'statusLabel' => __('Status')])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.stacked-bar :title="__('Spesen (€) :per nach Kategorie', ['per' => $periodPhrase])" unit="€" :series="$monthlyCategorySeries" :bands="$categoryBands" :x-label="$periodAxis" />
        <x-charts.bar-h :title="__('Top-Verursacher (Top 15)')" unit="€" :series="$topSpenderSeries" :x-label="__('Mitarbeiter')" :y-label="__('Brutto (€)')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Summe (Brutto)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($grandTotal, 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Mitarbeiter')" :value="count($totalsPerUser)" />
        <x-kpi-tile :label="__('Kategorien')" :value="count($totalsPerCategory)" />
        <x-kpi-tile :label="__('Monate')" :value="count($months)" />
    </div>

    <x-card class="overflow-x-auto">
        @if (empty($rows))
            <x-empty-state icon="receipt_long"
                           :title="__('Keine Spesen im gewählten Zeitraum.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        @foreach ($months as $m)
                            <th class="text-right whitespace-nowrap">{{ \Carbon\Carbon::createFromFormat('Y-m', $m)->locale(app()->getLocale())->translatedFormat('M Y') }}</th>
                        @endforeach
                        <th class="text-right">{{ __('Summe') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-semibold">
                        <td colspan="2">{{ __('Gesamt') }}</td>
                        @foreach ($months as $m)
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totalsPerMonth[$m] ?? 0, 2, withThousandsSeparator: true) }}</td>
                        @endforeach
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($grandTotal, 2, withThousandsSeparator: true) }}</td>
                    </tr>
                </x-slot:foot>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-semibold whitespace-nowrap">{{ $row['user'] }}</td>
                            <td>
                                <span class="inline-flex items-center gap-1">
                                    @if (! empty($row['icon']))
                                        <x-icon :name="$row['icon']" class="text-{{ $row['color'] ?: 'primary' }}" />
                                    @endif
                                    {{ $row['category'] }}
                                </span>
                            </td>
                            @foreach ($months as $m)
                                <td class="text-right tabular-nums">
                                    @if (isset($row['months'][$m]))
                                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['months'][$m], 2, withThousandsSeparator: true) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-right tabular-nums font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['total'], 2, withThousandsSeparator: true) }}</td>
                        </tr>
                    @endforeach
            </x-table>
        @endif
    </x-card>

    @if (! empty($totalsPerCategory))
        <x-card>
            <h3 class="font-['Space_Grotesk'] text-lg font-semibold mb-3">{{ __('Top-Kategorien') }}</h3>
            <div class="grid gap-2 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                @php arsort($totalsPerCategory); @endphp
                @foreach ($totalsPerCategory as $cat => $sum)
                    <div class="flex items-center justify-between rounded-box bg-base-200/50 px-3 py-2">
                        <span class="truncate">{{ $cat }}</span>
                        <span class="tabular-nums font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($sum, 2, withThousandsSeparator: true) }} €</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

</x-page-shell>
@endsection

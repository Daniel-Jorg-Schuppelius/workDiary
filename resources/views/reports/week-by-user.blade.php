@extends('layouts.app')
@section('title', __('Woche pro Mitarbeiter') . ' — ' . $weekLabel)
@section('nav-title', __('Woche pro Mitarbeiter') . ' — ' . $weekLabel)

@section('content')
@php
    $fmt = fn (int $min): string => $min <= 0 ? '–' : \App\Support\Formats::duration($min, 'clock', withUnit: false);
    $money = function (float $val): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($val, 2, withThousandsSeparator: true) . ' €';
    };
    $fmtChart = fn (int|float $min): string => \App\Support\Formats::duration((int) $min, 'clock', withUnit: false);
    $linkParams = array_filter(array_merge(
        ['scope' => $seesAll ? $scope : null, 'week' => $activeKey],
        $standardFilters->toQueryParams(),
    ));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Stunden je Mitarbeiter und Tag in der ausgewählten Kalenderwoche.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.week-by-user', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.week-by-user', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>XLSX</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.week-by-user', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($seesAll)
        <x-filter-bar :action="route('reports.week-by-user')" :reset="route('reports.week-by-user')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
            @include('reports._standard_filters', ['idPrefix' => 'week-by-user'])
            {{-- Aktive Woche beim Umfiltern beibehalten. --}}
            <input type="hidden" name="week" value="{{ $activeKey }}">
        </x-filter-bar>
    @endif

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.heatmap
            :title="__('Stunden je Mitarbeiter und Wochentag')"
            unit="h"
            :rows="$heatmapRows"
            :col-labels="array_values($dayLabels)"
            :x-label="__('Mitarbeiter')"
            :format="$fmtChart"
        />
        <x-charts.stacked-bar :title="__('Stunden je Mitarbeiter nach Art')" unit="h" :series="$userKindSeries" :bands="$kindBands" :x-label="__('Mitarbeiter')" />
    </div>

    @if ($weeksTruncated ?? false)
        <div class="alert alert-warning text-sm">
            <span>{{ __('Der gewählte Zeitraum umfasst :total Wochen — es werden nur die ersten :shown Tabs angezeigt. Bitte engere die Auswahl im Header ein.', ['total' => $totalWeeks, 'shown' => count($weekTabs)]) }}</span>
        </div>
    @endif

    @if (count($weekTabs ?? []) > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($weekTabs as $tab)
                <a role="tab"
                   href="{{ route('reports.week-by-user', array_merge($linkParams, ['week' => $tab['key']])) }}"
                   class="tab whitespace-nowrap gap-1.5 {{ $tab['key'] === $activeKey ? 'tab-active' : '' }}">
                    <span class="font-semibold">{{ __('KW') }} {{ $tab['week'] }}</span>
                    <span class="text-[0.65rem] text-base-content/50 tabular-nums">{{ $tab['shortLabel'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $weekLabel }}</h2>
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $weekTotal > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $fmt($weekTotal) }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $weekRate > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $money($weekRate) }}
                    </span>
                </div>
            </div>
        </div>

        @if (count($byUser) === 0)
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">view_week</span>' :title="__('Keine Einträge in dieser Woche.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        @foreach ($dayLabels as $label)
                            <x-table.th sort type="duration" align="right">{{ $label }}</x-table.th>
                        @endforeach
                        <x-table.th sort type="duration" align="right">Σ {{ __('Stunden') }}</x-table.th>
                        <x-table.th sort type="number" align="right">Σ €</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>Σ {{ __('Tag') }}</td>
                        @foreach ($dayTotals as $m)
                            <td class="text-right">{{ $fmt($m) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($weekTotal) }}</td>
                        <td class="text-right">{{ $money($weekRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($byUser as $uid => $row)
                    <tr>
                        <td class="font-medium">{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                        @foreach ($row['days'] as $minutes)
                            <td class="text-right text-sm @if ($minutes === 0) opacity-30 @endif" data-sort-value="{{ (int) $minutes }}">{{ $fmt($minutes) }}</td>
                        @endforeach
                        <td class="text-right font-semibold" data-sort-value="{{ (int) $row['total'] }}">{{ $fmt($row['total']) }}</td>
                        <td class="text-right" data-sort-value="{{ (float) $row['rate'] }}">{{ $money($row['rate']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

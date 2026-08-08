{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : assets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Produktanalyse') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Produktanalyse'))

@section('content')
<x-index-page :subtitle="__('Defekte, offene Punkte und Aufwand je Asset, Produktgruppe oder Modell.')">
    <x-slot:actions>
        <x-icon-btn icon="download" tone="outline" size="sm"
                    :href="route('reports.assets', array_merge($standardFilters->toQueryParams(), array_filter(['category_code' => $categoryCode, 'manufacturer' => $manufacturer, 'group_by' => $groupBy]), ['export' => 'csv']))"
                    show-label>CSV</x-icon-btn>
        <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                    :href="route('reports.assets', array_merge($standardFilters->toQueryParams(), array_filter(['category_code' => $categoryCode, 'manufacturer' => $manufacturer, 'group_by' => $groupBy]), ['export' => 'pdf']))"
                    show-label>PDF</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('reports.assets')" :reset="route('reports.assets')">
        <x-filter-field :label="__('Ebene')" for="rep-group">
            <select id="rep-group" name="group_by" class="select select-sm select-bordered">
                <option value="asset"  @selected($groupBy === 'asset')>{{ __('Pro Asset') }}</option>
                <option value="group"  @selected($groupBy === 'group')>{{ __('Pro Produktgruppe') }}</option>
                <option value="model"  @selected($groupBy === 'model')>{{ __('Pro Modell') }}</option>
            </select>
        </x-filter-field>

        @include('reports._standard_filters', ['idPrefix' => 'assets'])

        <x-filter-field :label="__('Produktgruppe')" for="rep-category">
            <select id="rep-category" name="category_code" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($categories as $code)
                    <option value="{{ $code }}" @selected($categoryCode === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Hersteller')" for="rep-manufacturer">
            <select id="rep-manufacturer" name="manufacturer" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($manufacturers as $m)
                    <option value="{{ $m }}" @selected($manufacturer === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    {{-- Feature 002: Diagramme (Defekt-Pareto + Defektrate der aktiven Ebene) --}}
    @php
        $dimLabel = match ($groupBy) { 'group' => __('Produktgruppe'), 'model' => __('Modell'), default => __('Asset') };
        // Fernwartungs-Kennzahlen (MVP-476) nur zeigen, wenn welche vorliegen —
        // sonst reine Nullspalten für Orgs ohne RemoteSupport-Plugin.
        $hasMaintenance = collect($rows)->sum('maintenanceSessions') > 0;
    @endphp
    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Defekte im Zeitraum (Top 20)')" :unit="__('Defekte')" :series="$defectsSeries" :x-label="$dimLabel" :y-label="__('Defekte')" />
        <x-charts.bar :title="__('Defektrate (Top 15)')" unit="%" :series="$defectRateSeries" :x-label="$dimLabel" :y-label="__('Defektrate %')" />
    </div>
    @if ($hasMaintenance)
        <x-charts.bar-h :title="__('Wartungszeit je :dim (Top 15)', ['dim' => $dimLabel])" unit="h" :series="$maintenanceSeries"
                        :x-label="$dimLabel" :y-label="__('Wartungszeit')"
                        :note="__('Fernwartungssitzungen (AnyDesk/TeamViewer) je Gerät/Modell im Zeitraum.')" />
    @endif

    <x-card>
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if(empty($rows))
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' />
        @else
            <x-table table-sort="client" :caption="__('Produktanalyse')">
                <x-slot:head>
                    <x-table.th sort type="string">{{ match($groupBy) { 'group' => __('Produktgruppe'), 'model' => __('Modell'), default => __('Asset') } }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Assets') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Aufträge') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Offene Punkte') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Eskaliert') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Defekte') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Defektrate %') }}</x-table.th>
                    @if ($hasMaintenance)
                        <x-table.th sort type="number" align="right">{{ __('Wartungssitzungen') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Wartungszeit') }}</x-table.th>
                    @endif
                    <x-table.th sort type="date">{{ __('Letzter Vorfall') }}</x-table.th>
                </x-slot:head>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['assetCount'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['entryCount'] }}</td>
                        <td class="text-right tabular-nums">
                            @if($row['openIssueCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.open-issues', $row['drilldown']) }}" class="link link-hover">{{ $row['openIssueCount'] }}</a>
                            @else
                                {{ $row['openIssueCount'] }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            @if($row['escalationCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.open-issues', array_merge($row['drilldown'], ['escalated' => 1])) }}" class="link link-hover">{{ $row['escalationCount'] }}</a>
                            @else
                                {{ $row['escalationCount'] }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            @if($row['defectCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.protocols', $row['drilldown']) }}" class="link link-hover">{{ $row['defectCount'] }}</a>
                            @else
                                {{ $row['defectCount'] }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['defectRate'], 2, withThousandsSeparator: true) }}</td>
                        @if ($hasMaintenance)
                            <td class="text-right tabular-nums">{{ $row['maintenanceSessions'] }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $row['maintenanceMinutes'] }}">{{ $row['maintenanceMinutes'] > 0 ? \App\Support\Formats::duration($row['maintenanceMinutes'], 'clock') : '—' }}</td>
                        @endif
                        <td @if ($row['lastIncidentAt']) data-sort-value="{{ \Illuminate\Support\Carbon::parse($row['lastIncidentAt'])->format('Y-m-d') }}" @endif>{{ $row['lastIncidentAt'] ? \Illuminate\Support\Carbon::parse($row['lastIncidentAt'])->fdate() : '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-index-page>
@endsection

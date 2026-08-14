{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-types.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Auftragstypanalyse'))
@section('nav-title', __('Auftragstypanalyse'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Plan vs. Ist, Nacharbeit und Eskalation je Auftragstyp.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.entry-types', array_merge($standardFilters->toQueryParams(), array_filter(['entry_type_id' => \App\Support\Sqid::encode(\App\Models\EntryType::class, $entryTypeFilter)]), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.entry-types', array_merge($standardFilters->toQueryParams(), array_filter(['entry_type_id' => \App\Support\Sqid::encode(\App\Models\EntryType::class, $entryTypeFilter)]), ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.entry-types', array_merge($standardFilters->toQueryParams(), array_filter(['entry_type_id' => \App\Support\Sqid::encode(\App\Models\EntryType::class, $entryTypeFilter)]), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.entry-types')" :reset="route('reports.entry-types')">
        @include('reports._standard_filters', [
            'idPrefix' => 'entry-types',
            'statusOptions' => \App\Http\Controllers\Reporting\EntryTypeAnalysisReportController::statusOptions(),
        ])

        {{-- Typ-Drilldown-Parameter (bewusst NICHT im Standard-Set — der Report gliedert nach Typ). --}}
        <x-filter-field :label="__('Auftragstyp')" for="rep-entry-type">
            <select id="rep-entry-type" name="entry_type_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($entryTypes as $entryType)
                    <option value="{{ $entryType->sqid }}" @selected(\App\Support\Sqid::encode(\App\Models\EntryType::class, $entryTypeFilter) === $entryType->sqid)>{{ $entryType->label }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    {{-- Feature 002: Diagramme (Plan/Ist + Überzugsquote je Auftragstyp) --}}
    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Plan vs. Ist je Auftragstyp')" :unit="__('Min.')" :series="$planVsIstSeries" :x-label="__('Auftragstyp')" :y-label="__('Ø Ist (Min.)')" :y2-label="__('Ø Plan (Min.)')" />
        <x-charts.bar-h :title="__('Überzugsquote je Auftragstyp (Top 15)')" unit="%" :series="$overrunSeries" :x-label="__('Auftragstyp')" :y-label="__('Überzug %')" />
    </div>

    <x-card>
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if(empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Auftragsdaten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Auftragstyp') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Aufträge') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ø Plan (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ø Ist (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan/Ist') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Überzug') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Überzug %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Nacharbeit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Nacharbeit %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Escalation %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('First-Time-Right %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Median Ist') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('P90 Ist') }}</x-table.th>
                        {{-- Wirtschaftlichkeits-Ranking (Vollaudit 2026-07, N7). --}}
                        <x-table.th sort type="number" align="right">{{ __('DB') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('DB je Auftrag') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach($rows as $row)
                    @php
                        $ratio = $row['planActualRatio'];
                        $ratioClass = $ratio === null ? 'text-base-content/50' : ($ratio <= 1.0 ? 'text-success' : ($ratio <= 1.2 ? 'text-warning' : 'text-error'));
                        $drilldownHref = route('diary.index', array_filter([
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $customerId),
                            'entry_type' => $row['entryTypeId'] > 0 ? \App\Support\Sqid::encode(\App\Models\EntryType::class, $row['entryTypeId']) : null,
                            'status' => $statusFilter,
                        ]));
                        $reportDrilldown = array_filter([
                            'entry_type_id' => $row['entryTypeId'] > 0 ? $row['entryTypeId'] : null,
                            'customer_id' => $customerId,
                            'user_id' => $userId,
                            'status' => $statusFilter,
                        ]);
                    @endphp
                    <tr>
                        <td class="font-medium">
                            <a href="{{ $drilldownHref }}" class="link link-hover">{{ $row['entryTypeName'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['entryCount'] }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgPlannedMinutes'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums {{ $ratioClass }}">{{ $ratio === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($ratio, 3, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ $row['overrunCount'] }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['overrunShare'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.entry-types.drilldown.protocols', $reportDrilldown) }}" class="link link-hover">{{ $row['reworkCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['reworkShare'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.entry-types.drilldown.open-issues', array_merge($reportDrilldown, ['escalated' => 1])) }}" class="link link-hover">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['escalationShare'], 2, withThousandsSeparator: true) }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['firstTimeRightShare'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['medianActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['p90ActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums {{ $row['contribution'] < 0 ? 'text-error' : '' }}" title="{{ __('Erlös :revenue · Kosten :cost', ['revenue' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['revenue'], 2, withThousandsSeparator: true) . ' €', 'cost' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['cost'], 2, withThousandsSeparator: true) . ' €']) }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['contribution'], 2, withThousandsSeparator: true) }} €</td>
                        <td class="text-right tabular-nums {{ $row['contributionPerEntry'] < 0 ? 'text-error' : '' }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['contributionPerEntry'], 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bwa.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Betriebswirtschaftliche Auswertung (Feature 142, MVP-709): Gruppen mit
  Zwischensummen, Vergleichsmodus (Vorjahr/Vormonat/Monatsraster/Budget),
  Kostenstellen-Filter. Nicht zugeordnete Konten stehen sichtbar unten.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.bwa.title'))
@section('nav-title', __('accounting.reports.card.bwa.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    @php
        $query = request()->query();
        $valueKey = $compare === \App\Services\Accounting\Reports\BwaBuilder::COMPARE_MONTHS
            ? \App\Services\Accounting\Reports\BwaBuilder::COL_TOTAL
            : \App\Services\Accounting\Reports\BwaBuilder::COL_ACTUAL;
        $columnCount = 1 + count($columns) + ($hasDelta ? 2 : 0);
    @endphp
    <x-index-page overflow="clip" :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.bwa', array_merge($query, ['export' => 'csv']))" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.bwa', array_merge($query, ['export' => 'xlsx']))" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.bwa', array_merge($query, ['export' => 'pdf']))" :label="__('PDF')" />
            <x-icon-btn icon="edit_calendar" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.budget.index')" :label="__('accounting.budget.title')" />
        </x-slot:actions>

        <x-filter-bar :action="route('reports.accounting.bwa')" :reset="route('reports.accounting.bwa')">
            <x-date-range class="w-80 shrink-0" :label="false"
                          from-name="from" to-name="to"
                          :from="$from->toDateString()" :to="$to->toDateString()"
                          :from-label="__('Von')" :to-label="__('Bis')" />
            <x-filter-field :label="__('accounting.bwa.filter.compare')" for="bwa-compare">
                <select id="bwa-compare" name="compare" class="select select-sm select-bordered shrink-0">
                    @foreach ($compareModes as $mode)
                        <option value="{{ $mode }}" @selected($compare === $mode)>{{ __('accounting.bwa.compare.' . $mode) }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($costCenters->isNotEmpty())
                <x-filter-field :label="__('accounting.bwa.filter.cost_center')" for="bwa-cost-center">
                    <select id="bwa-cost-center" name="cost_center" class="select select-sm select-bordered shrink-0">
                        <option value="">{{ __('accounting.bwa.filter.all_cost_centers') }}</option>
                        @foreach ($costCenters as $center)
                            <option value="{{ $center->sqid }}" @selected($costCenter !== null && (int) $center->id === (int) $costCenter->id)>{{ $center->code }} · {{ $center->label }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
        </x-filter-bar>

        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <div>
                <div>{{ __('accounting.bwa.hint') }}</div>
                <div class="text-xs text-muted">
                    {{ __('accounting.bwa.scheme.' . ($scheme ?? 'none')) }}
                    @if ($compare_range !== null)
                        · {{ __('accounting.bwa.compare_range', ['from' => $compare_range[0]->fdate(), 'to' => $compare_range[1]->fdate()]) }}
                    @endif
                </div>
            </div>
        </div>

        @if ($unmapped_count > 0)
            <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
                <x-icon name="rule" />
                <span>{{ __('accounting.bwa.unmapped.hint', ['count' => $unmapped_count]) }}</span>
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('accounting.bwa.subtotal.total_output')" :value="$subtotals['total_output'][$valueKey]" tone="success" format="none" />
            <x-kpi-tile :label="__('accounting.bwa.subtotal.operating_result')" :value="$subtotals['operating_result'][$valueKey]"
                        :tone="(float) $subtotals['operating_result'][$valueKey] < 0 ? 'error' : 'primary'" format="none" />
            <x-kpi-tile :label="__('accounting.bwa.subtotal.result_before_tax')" :value="$subtotals['result_before_tax'][$valueKey]"
                        :tone="(float) $subtotals['result_before_tax'][$valueKey] < 0 ? 'error' : 'primary'" format="none" />
        </div>

        <x-charts.bar :title="__('accounting.bwa.chart.' . ($compare === \App\Services\Accounting\Reports\BwaBuilder::COMPARE_MONTHS ? 'months' : 'groups'))"
                      unit="€" :series="$chartSeries"
                      :x-label="__('accounting.bwa.column.row')" :y-label="__('accounting.bwa.column.actual')"
                      :y2-label="$chartSecondLabel" :computed-at="now()" />

        <x-table scroll="flex" table-sort="client" :zebra="false" :caption="__('accounting.reports.card.bwa.title')">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('accounting.bwa.column.row') }}</x-table.th>
                    @foreach ($columns as $column)
                        <x-table.th sort type="number" align="right">{{ $column['label'] }}</x-table.th>
                    @endforeach
                    @if ($hasDelta)
                        <x-table.th sort type="number" align="right">{{ __('accounting.bwa.column.delta') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('accounting.bwa.column.delta_pct') }}</x-table.th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr @class([
                    'hover',
                    'font-semibold bg-base-200/60' => $row['kind'] === 'subtotal',
                    'font-medium' => $row['kind'] === 'group' || $row['kind'] === 'unmapped',
                    'text-warning' => $row['kind'] === 'unmapped',
                ])>
                    <td class="{{ $row['depth'] > 0 ? 'pl-8 text-sm' : '' }}">
                        @if ($row['account'] !== null)
                            <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">{{ $row['label'] }}</a>
                        @else
                            {{ $row['label'] }}
                        @endif
                    </td>
                    @foreach ($columns as $column)
                        @php($value = $row['values'][$column['key']] ?? '0.00')
                        <td class="text-right font-mono {{ (float) $value === 0.0 && $row['depth'] > 0 ? 'text-muted' : '' }}" data-sort-value="{{ $value }}">{{ $value }}</td>
                    @endforeach
                    @if ($hasDelta)
                        <td class="text-right font-mono {{ (float) ($row['delta'] ?? 0) < 0 ? 'text-error' : '' }}" data-sort-value="{{ $row['delta'] ?? 0 }}">{{ $row['delta'] ?? '—' }}</td>
                        <td class="text-right font-mono" data-sort-value="{{ $row['delta_pct'] ?? 0 }}">{{ $row['delta_pct'] !== null ? $row['delta_pct'] . ' %' : '—' }}</td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$columnCount" :title="__('accounting.reports.empty')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

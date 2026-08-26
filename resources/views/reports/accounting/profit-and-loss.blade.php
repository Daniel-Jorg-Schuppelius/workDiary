{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : profit-and-loss.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ergebnisrechnung (Feature 125, MVP-676) nach Kontengruppen — ausdrücklich
  keine testierte GuV. Vergleichsspalten und Kostenstellen-Filter wie in der
  BWA (Feature 142, MVP-709).
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.pnl.title'))
@section('nav-title', __('accounting.reports.card.pnl.title'))

@section('content')
    @php
        $query = request()->query();
        $hasCompare = $compare_totals !== null;
    @endphp
    <x-index-page :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', array_merge($query, ['export' => 'csv']))" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', array_merge($query, ['export' => 'xlsx']))" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', array_merge($query, ['export' => 'pdf']))" :label="__('PDF')" />
        </x-slot:actions>

        <x-filter-bar :action="route('reports.accounting.profit-and-loss')" :reset="route('reports.accounting.profit-and-loss')">
            <x-filter-field :label="__('accounting.bwa.filter.compare')" for="pnl-compare">
                <select id="pnl-compare" name="compare" class="select select-sm select-bordered shrink-0">
                    @foreach ($compareModes as $mode)
                        <option value="{{ $mode }}" @selected($compare === $mode)>{{ __('accounting.bwa.compare.' . $mode) }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($costCenters->isNotEmpty())
                <x-filter-field :label="__('accounting.bwa.filter.cost_center')" for="pnl-cost-center">
                    <select id="pnl-cost-center" name="cost_center" class="select select-sm select-bordered shrink-0">
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
            <span>
                {{ __('accounting.reports.pnl_hint') }}
                @if ($compare_range !== null)
                    · {{ __('accounting.bwa.compare_range', ['from' => $compare_range[0]->fdate(), 'to' => $compare_range[1]->fdate()]) }}
                @endif
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('accounting.reports.section.income')" :value="$income_total" format="none"
                        :hint="$hasCompare ? __('accounting.bwa.compare.' . $compare) . ': ' . $compare_totals['income_total'] : null" />
            <x-kpi-tile :label="__('accounting.reports.section.expense')" :value="$expense_total" format="none"
                        :hint="$hasCompare ? __('accounting.bwa.compare.' . $compare) . ': ' . $compare_totals['expense_total'] : null" />
            <x-kpi-tile :label="__('accounting.reports.column.result')" :value="$result" format="none"
                        :hint="$hasCompare ? __('accounting.bwa.compare.' . $compare) . ': ' . $compare_totals['result'] : null" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['rows' => $income, 'key' => 'income'], ['rows' => $expense, 'key' => 'expense']] as $group)
                <x-card :title="__('accounting.reports.section.' . $group['key'])" icon="table_rows">
                    <x-table :bare="true">
                        @if ($hasCompare)
                            <x-slot:head>
                                <tr>
                                    <th>{{ __('accounting.ledger.column.account') }}</th>
                                    <th class="text-right">{{ __('accounting.bwa.column.actual') }}</th>
                                    <th class="text-right">{{ __('accounting.bwa.compare.' . $compare) }}</th>
                                    <th class="text-right">{{ __('accounting.bwa.column.delta') }}</th>
                                    <th class="text-right">{{ __('accounting.bwa.column.delta_pct') }}</th>
                                </tr>
                            </x-slot:head>
                        @endif
                        @forelse ($group['rows'] as $row)
                            <tr class="hover">
                                <td>
                                    <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">
                                        {{ $row['account']->displayLabel() }}
                                    </a>
                                </td>
                                <td class="text-right font-mono">{{ $row['amount'] }}</td>
                                @if ($hasCompare)
                                    <td class="text-right font-mono">{{ $row['compare'] }}</td>
                                    <td class="text-right font-mono {{ (float) $row['delta'] < 0 ? 'text-error' : '' }}">{{ $row['delta'] }}</td>
                                    <td class="text-right font-mono">{{ $row['delta_pct'] !== null ? $row['delta_pct'] . ' %' : '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <x-table.empty :colspan="$hasCompare ? 5 : 2" :title="__('accounting.reports.empty')" compact />
                        @endforelse
                    </x-table>
                </x-card>
            @endforeach
        </div>
    </x-index-page>
@endsection

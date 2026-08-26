{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : budget.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Budgetpflege (Feature 142, MVP-709): Konto × Jahreswert/Monate je
  Geschäftsjahr und Kostenstelle. Bearbeiten im Dialog, Vorjahr-Ist als
  Budget, CSV/XLSX.
--}}

@extends('layouts.app')

@section('title', __('accounting.budget.title'))
@section('nav-title', __('accounting.budget.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    @php
        $query = array_filter(['year' => $year, 'cost_center' => $costCenter?->sqid]);
        $columnCount = 4 + count($months) + ($canEdit ? 1 : 0);
    @endphp
    <x-index-page overflow="clip" :subtitle="__('accounting.budget.subtitle', ['year' => $year])">
        <x-slot:actions>
            @if ($canEdit)
                <x-action-form :action="route('reports.accounting.budget.copy-previous-year', $query)" method="POST"
                               :confirm="__('accounting.budget.confirm.copy_previous', ['year' => $year - 1])">
                    <x-button type="submit" tone="ghost" size="sm">{{ __('accounting.budget.action.copy_previous') }}</x-button>
                </x-action-form>
            @endif
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.budget.index', $query + ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.budget.index', $query + ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="analytics" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.bwa', ['compare' => 'budget'])" :label="__('accounting.reports.card.bwa.title')" />
        </x-slot:actions>

        <x-filter-bar :action="route('reports.accounting.budget.index')" :reset="route('reports.accounting.budget.index')">
            <x-filter-field :label="__('accounting.budget.filter.year')" for="budget-year">
                <select id="budget-year" name="year" class="select select-sm select-bordered shrink-0">
                    @foreach ($years as $option)
                        <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($costCenters->isNotEmpty())
                <x-filter-field :label="__('accounting.bwa.filter.cost_center')" for="budget-cost-center">
                    <select id="budget-cost-center" name="cost_center" class="select select-sm select-bordered shrink-0">
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
            <span>{{ __('accounting.budget.hint.mode') }} {{ __('accounting.budget.hint.sign') }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('accounting.budget.total')" :value="$total" :tone="(float) $total < 0 ? 'error' : 'primary'" format="none" />
            <x-kpi-tile :label="__('accounting.budget.filter.year')" :value="$year" tone="neutral" format="none" />
        </div>

        <x-table scroll="flex" table-sort="client" :caption="__('accounting.budget.title')">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default="asc">{{ __('accounting.ledger.column.account') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.budget.column.mode') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('accounting.budget.column.year_value') }}</x-table.th>
                    @foreach ($months as $month)
                        <x-table.th sort type="number" align="right">{{ $month->translatedFormat('M') }}</x-table.th>
                    @endforeach
                    <x-table.th sort type="number" align="right">{{ __('accounting.bwa.column.total') }}</x-table.th>
                    @if ($canEdit)
                        <th class="text-right"></th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td data-sort-value="{{ $row['account']->number }}">
                        <span class="font-mono">{{ $row['account']->number }}</span> {{ $row['account']->name }}
                        <x-status-badge size="xs" :tone="$row['account']->type->tone()">{{ $row['account']->type->label() }}</x-status-badge>
                    </td>
                    <td class="text-sm text-muted">{{ $row['mode'] !== null ? __('accounting.budget.mode.' . $row['mode']) : '—' }}</td>
                    <td class="text-right font-mono" data-sort-value="{{ $row['year'] ?? 0 }}">{{ $row['year'] ?? '—' }}</td>
                    @foreach ($months as $month)
                        @php($value = $row['months'][$month->month] ?? null)
                        <td class="text-right font-mono {{ $value === null ? 'text-muted' : '' }}" data-sort-value="{{ $value ?? 0 }}">{{ $value ?? '—' }}</td>
                    @endforeach
                    <td class="text-right font-mono font-semibold" data-sort-value="{{ $row['total'] }}">{{ $row['total'] }}</td>
                    @if ($canEdit)
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger
                                            :href="route('reports.accounting.budget.edit', ['account' => $row['account']->sqid] + $query)"
                                            :label="__('accounting.budget.action.edit')" />
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$columnCount" :title="__('accounting.budget.empty')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

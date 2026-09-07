{{--
  Created on   : Mon Sep 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reconcile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abgleich je Rechnungsempfänger (Feature 152): welcher Empfänger hat offene
  Perioden, freie Lizenzpositionen oder eine Lücke zwischen beidem.
  Standard: nur Empfänger mit Klärungsbedarf.
--}}
@extends('layouts.app')
@section('title', __('resale.reconcile.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.reconcile.title')" :subtitle="__('resale.reconcile.subtitle')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="primary" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endcan
            <x-icon-btn icon="fact_check" tone="ghost" size="sm" :href="route('finance.resale.periods.index')" show-label>{{ __('resale.periods.title') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.reconcile.kpi.open')" :value="$totals['open']" :tone="$totals['open'] > 0 ? 'error' : 'success'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.partial')" :value="$totals['partial']" :tone="$totals['partial'] > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.proposed')" :value="$totals['proposed']" :tone="$totals['proposed'] > 0 ? 'info' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.free')" :value="$fmt($totals['free'])" :tone="$totals['free'] > 0.001 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.missing')" :value="$fmt($totals['missing'])" :tone="$totals['missing'] > 0.001 ? 'error' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.surplus')" :value="$fmt($totals['surplus'])" :tone="$totals['surplus'] > 0.001 ? 'info' : 'neutral'" />
        </div>
        <p class="text-xs text-muted mb-3">{{ __('resale.reconcile.legend') }}</p>

        <x-filter-bar :action="route('finance.resale.reconcile.index')" :reset="route('finance.resale.reconcile.index')">
            <select name="show" class="select select-sm select-bordered w-56" aria-label="{{ __('resale.field.status') }}">
                <option value="problems" @selected($filter === 'problems')>{{ __('resale.reconcile.filter_problems') }}</option>
                <option value="all" @selected($filter === 'all')>{{ __('resale.reconcile.filter_all') }}</option>
            </select>
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.billed_to') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.col.subscriptions') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.open') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.partial') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.proposed') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.free') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.missing') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.reconcile.kpi.surplus') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td>
                        @if ($row['customer'] !== null)
                            <a href="{{ route('finance.resale.reconcile.show', $row['customer']) }}" class="link link-hover font-medium">{{ $row['name'] }}</a>
                        @else
                            {{ $row['name'] }}
                            <span class="badge badge-warning badge-outline badge-xs ml-1" title="{{ __('resale.reconcile.no_customer_hint') }}">{{ __('resale.reconcile.no_customer') }}</span>
                        @endif
                        @if ($row['subscriptions'] === 0 && $row['lines'] > 0)
                            <span class="block text-xs text-muted">{{ trans_choice('resale.reconcile.lines_without_subscription', $row['lines'], ['count' => $row['lines']]) }}</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $row['subscriptions'] }}</td>
                    <td class="text-right tabular-nums"><span @class(['text-error font-medium' => $row['open'] > 0])>{{ $row['open'] }}</span></td>
                    <td class="text-right tabular-nums"><span @class(['text-warning font-medium' => $row['partial'] > 0])>{{ $row['partial'] }}</span></td>
                    <td class="text-right tabular-nums"><span @class(['text-info' => $row['proposed'] > 0])>{{ $row['proposed'] }}</span></td>
                    <td class="text-right tabular-nums"><span @class(['text-warning font-medium' => $row['free'] > 0.001])>{{ $fmt($row['free']) }}</span></td>
                    <td class="text-right tabular-nums"><span @class(['text-error font-medium' => $row['missing'] > 0.001])>{{ $fmt($row['missing']) }}</span></td>
                    <td class="text-right tabular-nums"><span @class(['text-info font-medium' => $row['surplus'] > 0.001])>{{ $fmt($row['surplus']) }}</span></td>
                    <td class="text-right">
                        @if ($row['customer'] !== null)
                            <x-icon-btn icon="compare_arrows" size="xs" tone="ghost" :href="route('finance.resale.reconcile.show', $row['customer'])" :title="__('resale.reconcile.action.open')" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9" icon="task_alt" :title="__('resale.reconcile.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

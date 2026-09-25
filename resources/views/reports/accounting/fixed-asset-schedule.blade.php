{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fixed-asset-schedule.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlagenspiegel (Feature 133, MVP-890): Entwicklung von AK/HK und kumulierter
  AfA eines Geschäftsjahres aus dem AfA-Plan.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.fixed_asset_schedule.title'))
@section('nav-title', __('accounting.reports.card.fixed_asset_schedule.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    @php $columns = \App\Services\Accounting\Reports\FixedAssetScheduleBuilder::COLUMNS; @endphp
    <x-index-page overflow="clip" :subtitle="__('accounting.reports.fixed_asset_schedule.subtitle', ['year' => $label, 'from' => $starts_on->fdate(), 'to' => $ends_on->fdate()])">
        <x-slot:actions>
            <form method="GET" action="{{ route('reports.accounting.fixed-asset-schedule') }}" class="flex items-center gap-1">
                <select name="year" class="select select-sm select-bordered" aria-label="{{ __('accounting.reports.fixed_asset_schedule.year') }}">
                    @for ($y = $currentYear + 1; $y >= $currentYear - 10; $y--)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endfor
                </select>
                <x-icon-btn icon="search" tone="ghost" size="sm" type="submit" :aria-label="__('accounting.reports.fixed_asset_schedule.show')" />
            </form>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.fixed-asset-schedule', ['year' => $year, 'export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.fixed-asset-schedule', ['year' => $year, 'export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.fixed-asset-schedule', ['year' => $year, 'export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <p class="text-xs text-muted">{{ __('accounting.reports.fixed_asset_schedule.hint') }}</p>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.fixed_assets.column.no') }}</th>
                    <th>{{ __('accounting.fixed_assets.column.name') }}</th>
                    @foreach ($columns as $column)
                        <th class="text-right">{{ __('accounting.reports.fixed_asset_schedule.' . $column) }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-mono">
                        <a class="link" href="{{ route('finance.accounting.fixed-assets.show', $row['asset']) }}">{{ $row['asset']->asset_no }}</a>
                    </td>
                    <td>{{ $row['asset']->name }}</td>
                    @foreach ($columns as $column)
                        <td class="text-right font-mono">{{ $row['values'][$column]->format() }}</td>
                    @endforeach
                </tr>
            @empty
                <x-table.empty :colspan="2 + count($columns)" icon="inventory" :title="__('accounting.reports.empty')" compact />
            @endforelse
            <tr class="font-semibold">
                <td colspan="2">{{ __('accounting.ledger.entry.total') }}</td>
                @foreach ($columns as $column)
                    <td class="text-right font-mono">{{ $totals[$column]->format() }}</td>
                @endforeach
            </tr>
        </x-table>
    </x-index-page>
@endsection

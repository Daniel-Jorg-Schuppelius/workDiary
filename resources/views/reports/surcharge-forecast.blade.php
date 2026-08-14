{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : surcharge-forecast.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('reporting.surcharge_forecast.title'))
@section('nav-title', __('reporting.surcharge_forecast.title'))

@section('content')
@php
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $queryBase = array_filter([
        'months' => request()->query('months'),
        'user' => request()->query('user'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('reporting.surcharge_forecast.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.surcharge-forecast', array_merge($queryBase, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.surcharge-forecast', array_merge($queryBase, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.surcharge-forecast')" :reset="route('reports.surcharge-forecast')">
        <x-filter-field :label="__('reporting.surcharge_forecast.months_label')" for="sf-months">
            <select id="sf-months" name="months" class="select select-sm select-bordered" data-autosubmit>
                @foreach ([3, 6, 12] as $option)
                    <option value="{{ $option }}" @selected($months === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('reporting.surcharge_forecast.user_label')" for="sf-user" class="min-w-44">
            <select id="sf-user" name="user" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('reporting.surcharge_forecast.all_users') }}</option>
                @foreach ($userOptions as $option)
                    <option value="{{ $option['sqid'] }}" @selected($userId === $option['id'])>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-card>
        @if ($forecast['rows'] === [])
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">query_stats</span>'
                           :title="__('reporting.surcharge_forecast.empty')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('reporting.surcharge_forecast.col_wage_type') }}</x-table.th>
                        <x-table.th>{{ __('reporting.surcharge_forecast.col_label') }}</x-table.th>
                        @foreach ($forecast['months'] as $month)
                            <x-table.th align="right">{{ $month }}</x-table.th>
                        @endforeach
                        <x-table.th align="right">{{ __('reporting.surcharge_forecast.col_total') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td colspan="2">{{ __('Gesamt') }}</td>
                        @foreach ($forecast['months'] as $month)
                            <td class="text-right tabular-nums">{{ $fmtMin($forecast['totals'][$month] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right tabular-nums">{{ $fmtMin(array_sum($forecast['totals'])) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($forecast['rows'] as $row)
                    <tr>
                        <td class="font-mono text-xs">{{ $row['wage_type_code'] }}</td>
                        <td class="font-semibold">{{ $row['label'] }}</td>
                        @foreach ($forecast['months'] as $month)
                            <td class="text-right tabular-nums">{{ $fmtMin($row['minutes'][$month] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right tabular-nums font-semibold">{{ $fmtMin($row['total']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
        <p class="mt-2 text-xs text-base-content/50">{{ __('reporting.surcharge_forecast.note') }}</p>
    </x-card>
</x-page-shell>
@endsection

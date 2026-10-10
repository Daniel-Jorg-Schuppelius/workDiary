{{--
  Created on   : Sat Sep 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : replacement-forecast.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Restwert- und Ersatzprognose (MVP-908): Anlagen am Ende der Nutzungsdauer, auslaufende Leasingverträge. --}}
@extends('layouts.app')

@php
    $fmt = static fn (string $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('title', __('accounting.reports.card.replacement_forecast.title'))
@section('nav-title', __('accounting.reports.card.replacement_forecast.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('accounting.reports.card.replacement_forecast.title')" :subtitle="__('accounting.reports.replacement.subtitle', ['from' => $asOf->fdate(), 'to' => $until->fdate(), 'inflation' => $inflation])"
                        back-route="reports.accounting.index" :back-label="__('Zurück')">
            <x-slot:actions>
                <form method="GET" action="{{ route('reports.accounting.replacement-forecast') }}" class="flex items-center gap-1">
                    <select name="years" class="select select-sm select-bordered" aria-label="{{ __('accounting.reports.replacement.horizon') }}">
                        @foreach ([1, 3, 5, 10, 15] as $y)
                            <option value="{{ $y }}" @selected($y === $horizon)>{{ __('accounting.reports.replacement.years', ['count' => $y]) }}</option>
                        @endforeach
                    </select>
                    <x-icon-btn icon="search" tone="ghost" size="sm" type="submit" :label="__('accounting.reports.replacement.horizon')" />
                </form>
                <x-report-export :url="fn (string $format) => route('reports.accounting.replacement-forecast', ['years' => $horizon, 'export' => $format])" tone="ghost" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($years !== [])
        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($years as $year => $sum)
                <x-kpi-tile :label="__('accounting.reports.replacement.year_total', ['year' => $year])" :value="$fmt($sum)" />
            @endforeach
        </div>
    @endif

    <x-card :title="__('accounting.reports.replacement.assets')" :count="count($assets)">
        @if ($assets === [])
            <p class="text-sm text-muted">{{ __('accounting.reports.replacement.no_assets') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('accounting.fixed_assets.column.no') }}</th><th>{{ __('accounting.fixed_assets.column.name') }}</th><th>{{ __('accounting.reports.replacement.ends_on') }}</th><th class="text-right">{{ __('accounting.reports.replacement.book_value') }}</th><th class="text-right">{{ __('accounting.reports.replacement.replacement') }}</th></tr>
                </x-slot:head>
                @foreach ($assets as $row)
                    <tr>
                        <td class="tabular-nums">{{ $row['asset']->asset_no }}</td>
                        <td>{{ $row['asset']->name }}</td>
                        <td>{{ $row['ends_on']->fdate() }}@if ($row['overdue']) <x-status-badge tone="warning" size="sm">{{ __('accounting.reports.replacement.overdue') }}</x-status-badge>@endif</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['book_value']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['replacement']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
        <p class="mt-2 text-xs text-muted">{{ __('accounting.reports.replacement.hint') }}</p>
    </x-card>

    @if ($leases !== [])
        <x-card :title="__('accounting.reports.replacement.leases')" :count="count($leases)">
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('accounting.reports.replacement.contract') }}</th><th>{{ __('accounting.reports.replacement.partner') }}</th><th>{{ __('accounting.reports.replacement.ends_on') }}</th><th class="text-right">{{ __('accounting.reports.replacement.residual') }}</th></tr>
                </x-slot:head>
                @foreach ($leases as $row)
                    <tr>
                        <td class="tabular-nums">{{ $row['contract']->number }}</td>
                        <td>{{ $row['contract']->partner_name }}</td>
                        <td>{{ $row['ends_on']->fdate() }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['residual']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

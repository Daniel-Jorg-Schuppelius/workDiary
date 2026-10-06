{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-retention-cohort.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Kohorte :year', ['year' => $cohort]))
@section('nav-title', __('Kohorte :year', ['year' => $cohort]))

@section('content')
@php
    $backParams = array_merge(
        $standardFilters->toQueryParams(),
        $lostDays !== 365 ? ['lost_days' => $lostDays] : [],
    );
    $selfParams = array_merge($backParams, array_filter(['cohort' => $cohort, 'year' => $year]));
    $customerLink = fn (int $id): string => route('reports.customer-project', array_merge($standardFilters->toQueryParams(), [
        'customer' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $id),
    ]));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Kunden mit Erstleistung im Jahr :year', ['year' => $cohort]) . ($year !== null ? ' · ' . __('Aktivität im Jahr :year', ['year' => $year]) : '') . ' · ' . $label"
                        :back="route('reports.customer-retention', $backParams)" :back-label="__('Zur Kundenbindung')">
            <x-slot:actions>
                <x-report-export :url="fn (string $format) => route('reports.customer-retention.drilldown', array_merge($selfParams, ['export' => $format]))" :formats="['csv', 'xlsx']" />
                <x-help-button topic="reports.customer-retention" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        @if ($rows === [])
            <x-empty-state icon="group_off" :title="__('Keine Kunden mit Erstleistung in diesem Jahr.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="date" align="right">{{ __('Erste Leistung') }}</x-table.th>
                        <x-table.th sort type="date" align="right">{{ __('Letzte Leistung') }}</x-table.th>
                        @if ($year !== null)
                            <x-table.th sort type="string" align="right">{{ __('Aktiv in :year', ['year' => $year]) }}</x-table.th>
                        @endif
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ $customerLink($row['customerId']) }}" class="link link-hover">{{ $row['customerName'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['firstActivity'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['lastActivity'] }}</td>
                        @if ($year !== null)
                            <td class="text-right">
                                @if ($row['activeInYear'])
                                    <x-status-badge tone="success">{{ __('ja') }}</x-status-badge>
                                @else
                                    <x-status-badge>{{ __('nein') }}</x-status-badge>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

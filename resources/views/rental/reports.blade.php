{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reports.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verleihbericht'))
@section('nav-title', __('Verleihbericht'))

@section('content')
<x-index-page :subtitle="__('Auslastung, Umsatz, Überfälligkeit und Schäden mit Drilldown bis zur Verleihakte.')">
    <x-slot:actions>
        <form method="POST" action="{{ route('rental.reports.snapshot', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
            @csrf
            <button type="submit" class="btn btn-sm">{{ __('Snapshot einfrieren') }}</button>
        </form>
    </x-slot:actions>

    <x-filter-bar :action="route('rental.reports.index')" :reset="route('rental.reports.index')">
        <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" />
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Verleihvorgänge')" :value="$caseCount" />
        <x-kpi-tile :label="__('Auslastung')" :value="$utilization . ' %'" />
        <x-kpi-tile :label="__('Umsatz (Positionen)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($revenueTotal, 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Schadensfälle')" :value="$damageCount" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Überfällige Rückgaben')" :value="$overdueCount" />
        <x-kpi-tile :label="__('Belegte Gerätetage')" :value="$rentedDays" />
        <x-kpi-tile :label="__('Wartungs-/Reinigungsblockaden')" :value="$maintenanceBlocked" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Umsatz nach Positionsart')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Art') }}</th><th class="text-right">{{ __('Betrag') }}</th></tr></x-slot:head>
                @forelse ($revenueByKind as $kind => $amount)
                    <tr>
                        <td>{{ \App\Enums\Rental\RentalChargeKind::tryFrom($kind)?->label() ?? $kind }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" :title="__('Keine Umsätze im Zeitraum.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Umsatz nach Kunde (Top 10)')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Kunde') }}</th><th class="text-right">{{ __('Betrag') }}</th></tr></x-slot:head>
                @forelse ($revenueByCustomer as $customer => $amount)
                    <tr>
                        <td>{{ $customer }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" :title="__('Keine Umsätze im Zeitraum.')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    <x-card :title="__('Schäden nach Gerät (Top 10)')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Gerät') }}</th><th class="text-right">{{ __('Schadensfälle') }}</th></tr></x-slot:head>
            @forelse ($damageByAsset as $asset => $count)
                <tr>
                    <td>{{ $asset }}</td>
                    <td class="text-right font-mono">{{ $count }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="2" :title="__('Keine Schäden im Zeitraum.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Eingefrorene Snapshots (P2)')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Zeitraum') }}</th><th>{{ __('Erstellt') }}</th><th class="text-right">{{ __('Umsatz') }}</th><th class="text-right">{{ __('Auslastung') }}</th></tr></x-slot:head>
            @forelse ($snapshots as $snapshot)
                <tr>
                    <td>{{ $snapshot->period_start->fdate() }} – {{ $snapshot->period_end->fdate() }}</td>
                    <td>{{ $snapshot->created_at?->fdatetime() }}</td>
                    <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) data_get($snapshot->payload, 'revenueTotal', 0), 2, withThousandsSeparator: true) }} €</td>
                    <td class="text-right font-mono">{{ data_get($snapshot->payload, 'utilization', 0) }} %</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Noch keine Snapshots eingefroren.')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection

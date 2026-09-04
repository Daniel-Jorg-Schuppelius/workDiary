{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : prices.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Preisprüfung (Feature 152, MVP-766 — aus 151 übernommen): je Produkt
  Einkauf laut Vertrag, aktueller Katalogpreis, UVP und Verkaufspreise
  der Abos; Hinweise, wo der Preis anzupassen ist.
--}}
@extends('layouts.app')
@section('title', __('resale.prices.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $money = static fn(?float $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $tones = ['below_purchase' => 'error', 'below_list' => 'warning', 'contract_above_catalog' => 'info', 'no_sales' => 'neutral'];
@endphp

@section('content')
    <x-index-page :title="__('resale.prices.title')" :subtitle="$catalogDate !== null ? __('resale.prices.subtitle', ['date' => $catalogDate->format('d.m.Y')]) : __('resale.prices.subtitle_no_catalog')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <p class="text-xs text-muted mb-2">{{ __('resale.prices.hint') }}</p>
        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.article') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.prices.subscriptions') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.prices.purchase_contract') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.prices.list_price') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.prices.uvp') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.prices.sale') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.margin') }}</x-table.th>
                    <x-table.th>{{ __('resale.prices.flags') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-medium">{{ $row['label'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['subscriptions'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['quantity'] }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['purchase_min']) }}@if ($row['purchase_max'] !== null && $row['purchase_max'] !== $row['purchase_min']) – {{ $money($row['purchase_max']) }}@endif</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['list_price']) }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['uvp']) }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">
                        {{ $money($row['sale_median']) }}
                        @if ($row['sale_min'] !== null && $row['sale_min'] !== $row['sale_max'])
                            <span class="block text-xs text-muted">{{ $money($row['sale_min']) }} – {{ $money($row['sale_max']) }}</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums whitespace-nowrap {{ ($row['margin'] ?? 0) < 0 ? 'text-error' : '' }}">{{ $money($row['margin']) }}</td>
                    <td>
                        @foreach ($row['flags'] as $flag)
                            <x-status-badge size="xs" :tone="$tones[$flag] ?? 'neutral'" :label="__('resale.prices.flag.' . $flag)" />
                        @endforeach
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9" icon="price_check" :title="__('resale.prices.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

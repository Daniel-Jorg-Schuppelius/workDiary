{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Marge je Produkt und je Rechnungsempfänger (Feature 152, MVP-765) über die
  fälligen Perioden: Soll-Verkauf, berechnet laut Bezügen, Soll-Einkauf.
--}}
@extends('layouts.app')
@section('title', __('resale.report.title'))
@section('nav-title', __('resale.title.menu'))

@php
    $money = static fn(float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('content')
    <x-index-page :title="__('resale.report.title')" :subtitle="__('resale.report.subtitle', ['date' => $today->format('d.m.Y')])">
        <x-slot:actions>
            <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('finance.resale.report.export')" show-label>{{ __('resale.export.action') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        @foreach ([['title' => __('resale.report.by_product'), 'rows' => $byProduct, 'first' => __('resale.field.article')], ['title' => __('resale.report.by_recipient'), 'rows' => $byRecipient, 'first' => __('resale.field.billed_to')]] as $block)
            <x-card :title="$block['title']" padding="p-0" class="mb-4">
                <x-table bare table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ $block['first'] }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.periods') }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.open') }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.expected_sale') }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.billed') }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.expected_purchase') }}</x-table.th>
                            <x-table.th class="text-right" sort type="number">{{ __('resale.report.margin') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($block['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['periods'] }}</td>
                            <td class="text-right tabular-nums {{ $row['open'] > 0 ? 'text-error' : '' }}">{{ $row['open'] }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['expected_sale']) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['billed']) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['expected_purchase']) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap {{ $row['billed'] - $row['expected_purchase'] < 0 ? 'text-error' : 'text-success' }}">{{ $money($row['billed'] - $row['expected_purchase']) }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" :title="__('resale.report.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>
        @endforeach
        <p class="text-xs text-muted">{{ __('resale.report.hint') }}</p>
    </x-index-page>
@endsection

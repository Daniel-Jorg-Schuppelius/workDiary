{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cost-centers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vertragswerte je Kostenstelle (MVP-894): laufende Verträge, wiederkehrende
  Werte auf Jahr und Monat normalisiert, je Währung eine Zeile.
--}}

@extends('layouts.app')

@section('title', __('contract.cost_center.title'))
@section('nav-title', __('contract.cost_center.title'))

@section('content')
    @php $fmt = static fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true); @endphp
    <x-index-page :subtitle="__('contract.cost_center.subtitle')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('contract.cost_center.field') }}</th>
                    <th class="text-right">{{ __('contract.cost_center.count') }}</th>
                    <th class="text-right">{{ __('contract.cost_center.yearly') }}</th>
                    <th class="text-right">{{ __('contract.cost_center.monthly') }}</th>
                    <th class="text-right">{{ __('contract.cost_center.once') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['cost_center'] ? $row['cost_center']->code . ' · ' . $row['cost_center']->label : __('contract.cost_center.none') }}</td>
                    <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['yearly']) }} {{ $row['currency'] }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['monthly']) }} {{ $row['currency'] }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['once']) }} {{ $row['currency'] }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('contract.cost_center.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

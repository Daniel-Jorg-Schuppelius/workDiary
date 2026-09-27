{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : supplier-ratings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lieferantenbewertung über Investitionen (MVP-928). Erwartet: $rows --}}
@extends('layouts.app')

@section('title', __('investment.supplier_rating.overview'))
@section('nav-title', __('investment.supplier_rating.overview'))

@php
    $fmt = static fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1);
@endphp

@section('content')
<x-index-page :subtitle="__('investment.supplier_rating.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('investments.index')" :label="__('Investitionen')" />
    </x-slot:actions>
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.supplier_rating.supplier') }}</th>
                    <th class="text-right">{{ __('investment.supplier_rating.count') }}</th>
                    <th class="text-right">{{ __('investment.supplier_rating.schedule_score') }}</th>
                    <th class="text-right">{{ __('investment.supplier_rating.cost_score') }}</th>
                    <th class="text-right">{{ __('investment.supplier_rating.quality_score') }}</th>
                    <th class="text-right">{{ __('investment.supplier_rating.overall') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['supplier']->name }}</td>
                    <td class="text-right tabular-nums">{{ $row['ratings'] }}</td>
                    <td class="text-right tabular-nums">{{ $fmt($row['schedule']) }}</td>
                    <td class="text-right tabular-nums">{{ $fmt($row['cost']) }}</td>
                    <td class="text-right tabular-nums">{{ $fmt($row['quality']) }}</td>
                    <td class="text-right tabular-nums font-semibold">{{ $fmt($row['overall']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="star_rate" :colspan="6" :title="__('investment.supplier_rating.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection

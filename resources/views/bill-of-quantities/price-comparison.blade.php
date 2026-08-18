{{--
  Created on   : Sun Aug 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : price-comparison.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('gaeb.comparison.title') . ' — ' . $bill->name)
@section('nav-title', __('gaeb.comparison.title'))

@section('content')
<x-index-page :subtitle="$bill->name">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.show', $bill)" show-label>{{ __('gaeb.show.back') }}</x-icon-btn>
    </x-slot:actions>

    @if ($comparison['bidders'] === [])
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">table_chart</span>'
                       :title="__('gaeb.comparison.empty_title')" :description="__('gaeb.comparison.empty_hint')" />
    @else
        {{-- Angebotssummen mit Rang und Abstand zum nächstgünstigeren Angebot. --}}
        <x-card>
            <div class="flex flex-wrap gap-6">
                @foreach ($comparison['bidders'] as $bidder)
                    <div>
                        <div class="text-xs uppercase opacity-60">
                            {{ __('gaeb.comparison.rank', ['rank' => $bidder['rank']]) }} · {{ $bidder['label'] }}
                        </div>
                        <div class="text-lg font-semibold tabular-nums">{{ $bidder['total']->format() }}</div>
                        @if ($bidder['gap_percent'] !== null)
                            <div class="text-xs {{ $bidder['unusually_low'] ? 'text-warning' : 'opacity-60' }}">
                                {{ __('gaeb.comparison.gap', ['percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $bidder['gap_percent'], 2)]) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (collect($comparison['bidders'])->contains('unusually_low', true))
                {{-- Aufklärung, nicht Ausschluss (§ 16d VOB/A, § 60 VgV). --}}
                <p class="mt-4 text-sm text-warning">{{ __('gaeb.comparison.unusually_low_hint') }}</p>
            @endif
            @unless ($comparison['complete'])
                <p class="mt-2 text-sm opacity-70">{{ __('gaeb.comparison.incomplete_hint') }}</p>
            @endunless
        </x-card>

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('gaeb.columns.reference_no') }}</th>
                    <th>{{ __('gaeb.columns.short_text') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.quantity') }}</th>
                    <th>{{ __('gaeb.columns.unit') }}</th>
                    @foreach ($comparison['bidders'] as $bidder)
                        <th class="text-right">{{ $bidder['label'] }}</th>
                    @endforeach
                    <th class="text-right">{{ __('gaeb.comparison.spread') }}</th>
                </x-slot:head>
                @foreach ($comparison['rows'] as $row)
                    <tr>
                        <td class="whitespace-nowrap font-mono text-xs">{{ $row['reference'] }}</td>
                        <td>{{ $row['short_text'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['quantity'] }}</td>
                        <td>{{ $row['unit'] }}</td>
                        @foreach ($comparison['bidders'] as $bidder)
                            @php $price = $row['prices'][$bidder['import_id']] ?? null; @endphp
                            <td class="text-right tabular-nums {{ $row['cheapest_import_id'] === $bidder['import_id'] ? 'font-semibold text-success' : '' }}">
                                {{-- Ein fehlender Preis ist kein Preis von null. --}}
                                {{ $price?->format() ?? '—' }}
                            </td>
                        @endforeach
                        <td class="text-right tabular-nums opacity-70">
                            {{ $row['spread_percent'] === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $row['spread_percent'], 2) . ' %' }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-index-page>
@endsection

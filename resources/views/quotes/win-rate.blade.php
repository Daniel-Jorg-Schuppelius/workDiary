{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : win-rate.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Trefferquote der Angebote (Feature 112, MVP-998). Variablen: $result, $groupBy, $label
--}}
@extends('layouts.app')
@section('title', __('quotes.win_rate.title'))
@section('nav-title', __('quotes.win_rate.title'))

@section('content')
@php
    $totals = $result['totals'];
    $percent = fn (?\CommonToolkit\ValueObjects\Percentage $rate): string => $rate?->format() ?? '–';
@endphp
<x-index-page :subtitle="__('quotes.win_rate.subtitle') . ' · ' . __('Zeitraum') . ': ' . $label">
    <x-slot:actions>
        <x-icon-btn icon="event_repeat" tone="outline" size="sm" :href="route('quotes.follow-ups.index')" show-label>{{ __('quotes.follow_up.title') }}</x-icon-btn>
    </x-slot:actions>
    <x-filter-bar :action="route('quotes.win-rate')" :reset="$groupBy !== 'customer' ? route('quotes.win-rate') : null">
        <x-filter-field :label="__('quotes.win_rate.group')" for="qwr-group" inline>
            <select id="qwr-group" name="group" class="select select-sm select-bordered" data-autosubmit>
                @foreach (\App\Services\Sales\QuoteWinRateReport::GROUPS as $group)
                    <option value="{{ $group }}" @selected($groupBy === $group)>{{ __('quotes.win_rate.group_' . $group) }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-tile :label="__('quotes.win_rate.rate')" :value="$percent($totals['rate'])"
                    :hint="__('quotes.win_rate.won') . ' ' . $totals['won'] . ' · ' . __('quotes.win_rate.lost') . ' ' . $totals['lost'] . ' · ' . __('quotes.win_rate.expired') . ' ' . $totals['expired']" />
        <x-kpi-tile :label="__('quotes.win_rate.volume_rate')" :value="$percent($totals['volume_rate'])" />
        <x-kpi-tile :label="__('quotes.win_rate.won_volume')" :value="$totals['won_volume']->format()" />
        <x-kpi-tile :label="__('quotes.win_rate.open')" :value="$result['open']['count'] . ' · ' . $result['open']['volume']->format()"
                    :hint="__('quotes.win_rate.open_hint')" />
    </div>

    <x-card>
        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('quotes.win_rate.group_' . $groupBy) }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.won') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.lost') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.expired') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.rate') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.won_volume') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.decided_volume') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('quotes.win_rate.volume_rate') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($result['groups'] as $row)
                <tr class="hover">
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['won'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['lost'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['expired'] }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $row['rate']?->getNumericValue() ?? -1 }}">{{ $percent($row['rate']) }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $row['won_volume']->getAmount() }}">{{ $row['won_volume']->format() }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $row['decided_volume']->getAmount() }}">{{ $row['decided_volume']->format() }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $row['volume_rate']?->getNumericValue() ?? -1 }}">{{ $percent($row['volume_rate']) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="8" :title="__('quotes.win_rate.empty')" compact />
            @endforelse
        </x-table>
        <p class="mt-2 text-xs text-muted">{{ __('quotes.win_rate.note') }}</p>
    </x-card>
</x-index-page>
@endsection

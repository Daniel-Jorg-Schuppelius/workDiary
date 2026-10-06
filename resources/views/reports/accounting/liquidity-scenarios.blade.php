{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : liquidity-scenarios.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Szenarien zur Liquiditätsvorschau (MVP-954). Erwartet: $scenarios --}}
@extends('layouts.app')

@section('title', __('accounting.reports.scenario.title'))
@section('nav-title', __('accounting.reports.scenario.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.scenario.subtitle')"
                  back-route="reports.accounting.liquidity-forecast" :back-label="__('accounting.reports.card.liquidity_forecast.title')">

        @if ($canEdit)
            <x-card :title="__('accounting.reports.scenario.new')">
                @include('reports.accounting._liquidity_scenario_form', ['scenario' => null])
            </x-card>
        @endif

        @foreach ($scenarios as $scenario)
            <x-card :title="$scenario->name" class="mt-4" padding="p-0" id="scenario-{{ $scenario->sqid }}">
                <x-slot:actions>
                    <x-icon-btn icon="insights" size="sm" :href="route('reports.accounting.liquidity-forecast', ['scenario' => $scenario->sqid])" show-label>{{ __('accounting.reports.scenario.open') }}</x-icon-btn>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('reports.accounting.liquidity-scenarios.destroy', $scenario) }}" data-confirm-dialog data-confirm-message="{{ __('accounting.reports.scenario.confirm_delete') }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-btn icon="delete" size="sm" type="submit" :title="__('Löschen')" />
                        </form>
                    @endif
                </x-slot:actions>
                <div class="px-4 py-3">
                    @include('reports.accounting._liquidity_scenario_form', ['scenario' => $scenario])
                </div>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.reports.scenario.field.label') }}</th>
                            <th>{{ __('accounting.reports.scenario.field.direction') }}</th>
                            <th>{{ __('accounting.reports.scenario.field.expected_on') }}</th>
                            <th class="text-right">{{ __('accounting.reports.scenario.field.amount') }}</th>
                            <th class="text-right">{{ __('Aktionen') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($scenario->items as $item)
                        <tr>
                            <td>{{ $item->label }}</td>
                            <td>{{ __('accounting.reports.scenario.direction.' . $item->direction) }}</td>
                            <td>{{ $item->expected_on->fdate() }}</td>
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->amount, 2, withThousandsSeparator: true) }}</td>
                            <td class="text-right">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('reports.accounting.liquidity-scenarios.items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="xs" type="submit" :title="__('Entfernen')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="list" :colspan="5" :title="__('accounting.reports.scenario.items_empty')" compact />
                    @endforelse
                </x-table>
                @if ($canEdit)
                <form method="POST" action="{{ route('reports.accounting.liquidity-scenarios.items.store', $scenario) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 px-4 py-3" data-entry-form>
                    @csrf
                    <x-input-field name="label" :id="'item-label-' . $scenario->sqid" :label="__('accounting.reports.scenario.field.label')" required />
                    <x-select-field name="direction" :id="'item-direction-' . $scenario->sqid" :label="__('accounting.reports.scenario.field.direction')" required>
                        <option value="out">{{ __('accounting.reports.scenario.direction.out') }}</option>
                        <option value="in">{{ __('accounting.reports.scenario.direction.in') }}</option>
                    </x-select-field>
                    <x-input-field name="expected_on" type="date" :id="'item-date-' . $scenario->sqid" :label="__('accounting.reports.scenario.field.expected_on')" required />
                    <x-input-field name="amount" type="number" step="0.01" min="0.01" :id="'item-amount-' . $scenario->sqid" :label="__('accounting.reports.scenario.field.amount')" required />
                    <x-button type="submit" tone="plain">{{ __('accounting.reports.scenario.add_item') }}</x-button>
                </form>
                @endif
            </x-card>
        @endforeach
    </x-index-page>
@endsection

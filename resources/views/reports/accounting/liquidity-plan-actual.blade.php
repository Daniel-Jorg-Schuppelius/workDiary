{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : liquidity-plan-actual.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist der Liquidität (MVP-984): festgehaltener Wochenstand gegen die
  tatsächlichen Kontobewegungen.
--}}
@extends('layouts.app')
@section('title', __('accounting.liquidity_plan.actual_title'))
@section('nav-title', __('accounting.liquidity_plan.actual_title'))
@section('content')
    @php($fmt = static fn (?\CommonToolkit\ValueObjects\Money $value): string => $value?->format(withSymbol: false) ?? '—')
    <x-index-page :subtitle="__('accounting.liquidity_plan.actual_subtitle')"
                  back-route="reports.accounting.liquidity-forecast" :back-label="__('accounting.reports.card.liquidity_forecast.title')">
        <x-slot:actions>
            @if ($canEdit)
                <x-action-form :action="route('reports.accounting.liquidity-plan.snapshot')" method="POST">
                    <x-button type="submit" tone="primary" size="sm" icon="photo_camera">{{ __('accounting.liquidity_plan.action.snapshot') }}</x-button>
                </x-action-form>
            @endif
        </x-slot:actions>
        @if ($snapshots->isNotEmpty())
            <x-filter-bar :action="route('reports.accounting.liquidity-plan.actual')" :reset="route('reports.accounting.liquidity-plan.actual')">
                <x-filter-field :label="__('accounting.liquidity_plan.field.snapshot')" for="plan-snapshot">
                    <select id="plan-snapshot" name="snapshot" class="select select-sm select-bordered shrink-0">
                        @foreach ($snapshots as $option)
                            <option value="{{ $option->sqid }}" @selected($snapshot?->is($option))>{{ $option->taken_on->fdate() }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        @endif

        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.reports.forecast.column.week') }}</th>
                    <th class="text-right">{{ __('accounting.liquidity_plan.column.planned_net') }}</th>
                    <th class="text-right">{{ __('accounting.liquidity_plan.column.actual_in') }}</th>
                    <th class="text-right">{{ __('accounting.liquidity_plan.column.actual_out') }}</th>
                    <th class="text-right">{{ __('accounting.liquidity_plan.column.actual_net') }}</th>
                    <th class="text-right">{{ __('accounting.liquidity_plan.column.deviation') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-medium">{{ $row['label'] }} <span class="text-xs text-muted">{{ \Carbon\CarbonImmutable::parse($row['from'])->fdate() }}</span></td>
                    <td class="text-right font-mono">{{ $fmt($row['planned_net']) }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['actual_in']) }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['actual_out']) }}</td>
                    <td class="text-right font-mono">{{ $fmt($row['actual_net']) }}</td>
                    <td class="text-right font-mono @if ($row['deviation']?->isNegative()) text-error @endif">{{ $fmt($row['deviation']) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('accounting.liquidity_plan.no_snapshot')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

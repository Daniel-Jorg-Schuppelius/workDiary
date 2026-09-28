{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : liquidity-plan.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Manuelle Planpositionen der 13-Wochen-Liquiditätsvorschau (MVP-984).
--}}
@extends('layouts.app')
@section('title', __('accounting.liquidity_plan.title'))
@section('nav-title', __('accounting.liquidity_plan.title'))
@section('content')
    <x-index-page :subtitle="__('accounting.liquidity_plan.subtitle')"
                  back-route="reports.accounting.liquidity-forecast" :back-label="__('accounting.reports.card.liquidity_forecast.title')">
        <x-slot:actions>
            @if ($canEdit)
                <x-icon-btn icon="add" tone="primary" size="sm" show-label data-entry-modal-trigger
                            :href="route('reports.accounting.liquidity-plan.create')" :label="__('accounting.liquidity_plan.action.add')" />
            @endif
        </x-slot:actions>

        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.liquidity_plan.field.label') }}</th>
                    <th>{{ __('accounting.liquidity_plan.field.starts_on') }}</th>
                    <th>{{ __('accounting.liquidity_plan.field.recurrence') }}</th>
                    <th class="text-right">{{ __('accounting.reports.forecast.column.inflow') }}</th>
                    <th class="text-right">{{ __('accounting.reports.forecast.column.outflow') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                <tr class="hover">
                    <td class="font-medium">{{ $item->label }}@if ($item->note) <span class="text-xs text-muted">· {{ $item->note }}</span>@endif</td>
                    <td>{{ $item->starts_on->fdate() }}@if ($item->ends_on) – {{ $item->ends_on->fdate() }}@endif</td>
                    <td>{{ $item->recurrence->label() }}</td>
                    <td class="text-right font-mono">{{ $item->direction === 'in' ? $item->planned_amount->format() : '' }}</td>
                    <td class="text-right font-mono">{{ $item->direction === 'out' ? $item->planned_amount->format() : '' }}</td>
                    <td class="text-right">
                        @if ($canEdit)
                            <x-action-form :action="route('reports.accounting.liquidity-plan.destroy', $item)" method="DELETE"
                                           :confirm="__('accounting.liquidity_plan.confirm.remove')" :confirm-label="__('Entfernen')">
                                <x-icon-btn type="submit" icon="delete" size="sm" tone="error" :label="__('Entfernen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('accounting.liquidity_plan.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : exchange-rates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Monatskurse für Fremdwährungsbelege (Feature 125, MVP-1012). Variablen: $rates
--}}
@extends('layouts.app')
@section('title', __('accounting.exchange_rates.title'))
@section('nav-title', __('accounting.exchange_rates.title'))

@section('content')
<x-index-page :subtitle="__('accounting.exchange_rates.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="rule" size="sm" show-label :href="route('finance.accounting.rules.index')">{{ __('accounting.rules.menu') }}</x-icon-btn>
        <x-icon-btn icon="upload" size="sm" show-label data-entry-modal-trigger
                    :href="route('finance.accounting.exchange-rates.import-form')">{{ __('accounting.exchange_rates.action.import') }}</x-icon-btn>
        <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger
                    :href="route('finance.accounting.exchange-rates.create')" :label="__('accounting.exchange_rates.action.add')" />
    </x-slot:actions>

    <x-card>
        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('accounting.exchange_rates.field.period') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.exchange_rates.field.currency') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('accounting.exchange_rates.field.rate') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.exchange_rates.field.source') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($rates as $rate)
                <tr class="hover">
                    <td class="tabular-nums" data-sort="{{ $rate->period->format('Y-m') }}">{{ $rate->period->format('m/Y') }}</td>
                    <td>{{ $rate->currency->value }}</td>
                    <td class="text-right tabular-nums">{{ $rate->rate->format() }}</td>
                    <td>{{ $rate->source ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="edit" size="sm" tone="ghost" data-entry-modal-trigger
                                    :href="route('finance.accounting.exchange-rates.edit', $rate)" :label="__('accounting.exchange_rates.action.edit')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('accounting.exchange_rates.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection

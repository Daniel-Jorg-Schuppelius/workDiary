{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : trial-balance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Summen- und Saldenliste (Feature 125, MVP-676): Vortrag, Periodenbewegung
  und Saldo je Konto — Grundlage jeder weiteren Auswertung.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.trial_balance.title'))
@section('nav-title', __('accounting.reports.card.trial_balance.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.trial-balance', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.trial-balance', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.trial-balance', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.number') }}</th>
                    <th>{{ __('accounting.ledger.column.name') }}</th>
                    <th class="text-right">{{ __('accounting.reports.column.opening') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.debit') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.credit') }}</th>
                    <th class="text-right">{{ __('accounting.reports.column.balance') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-mono">
                        <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">
                            {{ $row['account']->number }}
                        </a>
                    </td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-right font-mono">{{ $row['opening'] }}</td>
                    <td class="text-right font-mono">{{ $row['debit'] }}</td>
                    <td class="text-right font-mono">{{ $row['credit'] }}</td>
                    <td class="text-right font-mono">{{ $row['balance'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="table_rows" :title="__('accounting.reports.empty')" /></td></tr>
            @endforelse
            <tr class="font-semibold">
                <td colspan="2">{{ __('accounting.ledger.entry.total') }}</td>
                <td class="text-right font-mono">{{ $totals['opening'] }}</td>
                <td class="text-right font-mono">{{ $totals['debit'] }}</td>
                <td class="text-right font-mono">{{ $totals['credit'] }}</td>
                <td class="text-right font-mono">{{ $totals['balance'] }}</td>
            </tr>
        </x-table>
    </x-index-page>
@endsection

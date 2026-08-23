{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : liquidity.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bank/Kasse und Liquidität (Feature 125, MVP-676). Ist-Salden und Vorschau
  stehen getrennt — eine Summe aus beidem sähe aus wie ein Kontostand.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.liquidity.title'))
@section('nav-title', __('accounting.reports.card.liquidity.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.as_of', ['date' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.reports.kpi.cash')" :value="$cash_total" />
            <x-kpi-tile :label="__('accounting.reports.kpi.receivable')" :value="$receivable" />
            <x-kpi-tile :label="__('accounting.reports.kpi.payable')" :value="$payable" />
            <x-kpi-tile :label="__('accounting.reports.kpi.forecast')" :value="$forecast" />
        </div>

        <x-card :title="__('accounting.reports.section.balances')" icon="account_balance">
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.ledger.column.account') }}</th>
                        <th class="text-right">{{ __('accounting.reports.column.balance') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($accounts as $row)
                    <tr class="hover">
                        <td>
                            <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">
                                {{ $row['account']->displayLabel() }}
                            </a>
                        </td>
                        <td class="text-right font-mono">{{ $row['balance'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2"><x-empty-state icon="account_balance" :title="__('accounting.reports.empty')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['buckets' => $receivableAging, 'key' => 'receivable'], ['buckets' => $payableAging, 'key' => 'payable']] as $aging)
                <x-card :title="__('accounting.reports.aging.' . $aging['key'])" icon="schedule">
                    <x-table :bare="true">
                        @foreach (['not_due', 'd30', 'd60', 'd90', 'd90plus'] as $bucket)
                            <tr>
                                <td>{{ __('accounting.open_items.bucket.' . $bucket) }}</td>
                                <td class="text-right font-mono">{{ $aging['buckets'][$bucket] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endforeach
        </div>
    </x-index-page>
@endsection

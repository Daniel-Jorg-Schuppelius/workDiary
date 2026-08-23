{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : profit-and-loss.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ergebnisrechnung (Feature 125, MVP-676) nach Kontengruppen — ausdrücklich
  keine testierte GuV.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.pnl.title'))
@section('nav-title', __('accounting.reports.card.pnl.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.profit-and-loss', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.reports.pnl_hint') }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('accounting.reports.section.income')" :value="$income_total" />
            <x-kpi-tile :label="__('accounting.reports.section.expense')" :value="$expense_total" />
            <x-kpi-tile :label="__('accounting.reports.column.result')" :value="$result" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['rows' => $income, 'key' => 'income'], ['rows' => $expense, 'key' => 'expense']] as $group)
                <x-card :title="__('accounting.reports.section.' . $group['key'])" icon="table_rows">
                    <x-table :bare="true">
                        @forelse ($group['rows'] as $row)
                            <tr class="hover">
                                <td>
                                    <a class="link" href="{{ route('reports.accounting.account-ledger', ['account' => $row['account']->sqid]) }}">
                                        {{ $row['account']->displayLabel() }}
                                    </a>
                                </td>
                                <td class="text-right font-mono">{{ $row['amount'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-sm text-base-content/60">{{ __('accounting.reports.empty') }}</td></tr>
                        @endforelse
                    </x-table>
                </x-card>
            @endforeach
        </div>
    </x-index-page>
@endsection

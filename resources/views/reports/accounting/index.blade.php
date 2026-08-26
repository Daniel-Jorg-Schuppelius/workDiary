{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Finanzberichte (Feature 125, MVP-676). Alle Berichte teilen den globalen
  Header-Zeitraum und lesen ausschließlich festgeschriebene Buchungen.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.title'))
@section('nav-title', __('accounting.reports.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.subtitle')">
        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.reports.kpi.cash')" :value="$liquidity['cash_total']" />
            <x-kpi-tile :label="__('accounting.reports.kpi.receivable')" :value="$liquidity['receivable']" />
            <x-kpi-tile :label="__('accounting.reports.kpi.payable')" :value="$liquidity['payable']" />
            <x-kpi-tile :label="__('accounting.reports.kpi.findings')" :value="count($quality['findings'])" />
        </div>

        @if ($quality['findings'] !== [])
            <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
                <x-icon name="rule" />
                <div>
                    <div class="font-medium">{{ __('accounting.reports.quality.headline') }}</div>
                    <ul class="list-disc pl-4">
                        @foreach ($quality['findings'] as $finding)
                            <li>{{ $finding }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['route' => 'reports.accounting.trial-balance', 'icon' => 'table_rows', 'key' => 'trial_balance'],
                ['route' => 'reports.accounting.account-ledger', 'icon' => 'menu_book', 'key' => 'account_ledger'],
                ['route' => 'reports.accounting.vat', 'icon' => 'percent', 'key' => 'vat'],
                ['route' => 'reports.accounting.euer', 'icon' => 'savings', 'key' => 'euer'],
                ['route' => 'reports.accounting.recapitulative', 'icon' => 'public', 'key' => 'recapitulative'],
                ['route' => 'reports.accounting.profit-and-loss', 'icon' => 'trending_up', 'key' => 'pnl'],
                ['route' => 'reports.accounting.bwa', 'icon' => 'analytics', 'key' => 'bwa'],
                ['route' => 'reports.accounting.budget.index', 'icon' => 'edit_calendar', 'key' => 'budget'],
                ['route' => 'reports.accounting.liquidity', 'icon' => 'account_balance', 'key' => 'liquidity'],
                ['route' => 'reports.accounting.liquidity-forecast', 'icon' => 'timeline', 'key' => 'liquidity_forecast'],
                ['route' => 'reports.accounting.quality', 'icon' => 'fact_check', 'key' => 'quality'],
                ['route' => 'finance.accounting.journal.index', 'icon' => 'receipt_long', 'key' => 'journal'],
                ['route' => 'finance.accounting.open-items.index', 'icon' => 'account_balance_wallet', 'key' => 'open_items'],
            ] as $card)
                <a class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs hover:border-primary"
                   href="{{ route($card['route']) }}">
                    <div class="flex items-center gap-2">
                        <x-icon :name="$card['icon']" class="text-[1.2rem]" />
                        <span class="font-medium">{{ __('accounting.reports.card.' . $card['key'] . '.title') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">{{ __('accounting.reports.card.' . $card['key'] . '.text') }}</p>
                </a>
            @endforeach
        </div>
    </x-index-page>
@endsection

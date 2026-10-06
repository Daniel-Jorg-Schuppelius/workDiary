{{--
  Created on   : Sat Jun 13 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Zahlungsabgleich (Feature 045, Priorität 3): Liste der importierten
  Bankauszüge mit Kennzahlen (offen/zugeordnet) und Saldenketten-Badge.
--}}

@extends('layouts.app')

@section('title', __('bank.title.index'))
@section('nav-title', __('bank.title.menu'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('bank.subtitle.index')"
                  :badge="$importAvailable ? null : __('bank.import.error.unavailable_badge')"
                  badge-tone="warning"
                  :badge-title="$importAvailable ? null : \App\Services\Billing\FinancialFormatsSupport::unavailableMessage('bank.import.error.unavailable')">
        <x-slot:actions>
            @can('create', \App\Models\Finance\BankStatement::class)
                @if ($importAvailable)
                    <x-icon-btn icon="upload" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('finance.reconciliation.create')"
                                show-label>{{ __('bank.action.import') }}</x-icon-btn>
                @endif
            @endcan
            @can('viewAny', \App\Models\Finance\BankAccount::class)
                <x-icon-btn icon="account_balance" tone="ghost" size="sm"
                            :href="route('finance.bank-accounts.index')"
                            show-label>{{ __('bank.action.manage_accounts') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <div class="grid flex-none grid-cols-2 gap-3 sm:max-w-md">
            <x-card>
                <div class="text-sm text-muted">{{ __('bank.field.open') }}</div>
                <div class="text-2xl font-semibold text-warning">{{ $totals['open'] }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-muted">{{ __('bank.field.matched') }}</div>
                <div class="text-2xl font-semibold text-success">{{ $totals['matched'] }}</div>
            </x-card>
        </div>

        <x-table scroll="flex">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('bank.field.imported_at') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.format') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.account') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.period') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.balance_check') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('bank.field.open') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('bank.field.tx_count') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($statements as $statement)
                <tr class="hover">
                    <td>{{ $statement->created_at?->fdatetime() }}</td>
                    <td><x-status-badge :tone="$statement->source_format->tone()" :label="$statement->source_format->label()" /></td>
                    <td>{{ $statement->bankAccount?->label ?? '—' }}</td>
                    <td>
                        @if ($statement->period_from && $statement->period_to)
                            {{ $statement->period_from->fdate() }} – {{ $statement->period_to->fdate() }}
                        @else
                            —
                        @endif
                    </td>
                    <td><x-status-badge :tone="$statement->balance_check->tone()" :label="$statement->balance_check->label()" /></td>
                    <td class="text-right">
                        @if ($statement->open_count > 0)
                            <span class="text-warning font-medium">{{ $statement->open_count }}</span>
                        @else
                            0
                        @endif
                    </td>
                    <td class="text-right">{{ $statement->transactions_count }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                    :href="route('finance.reconciliation.show', $statement->sqid)" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8" :title="__('bank.empty.statements')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$statements" standing />
    </x-index-page>
@endsection

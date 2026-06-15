{{--
  Created on   : Sat Jun 13 2026
  License      : AGPL-3.0-or-later

  Eigene Bankkonten (Feature 045, finance.config). IBAN verschlüsselt at-rest.
--}}

@extends('layouts.app')

@section('title', __('bank.title.accounts'))
@section('nav-title', __('bank.title.accounts'))

@section('content')
    <x-index-page :subtitle="__('bank.subtitle.accounts')">
        <x-slot:actions>
            @can('create', \App\Models\Finance\BankAccount::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('finance.bank-accounts.create')"
                            show-label>{{ __('bank.action.new_account') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('bank.field.label') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.iban') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.bic') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.datev_account_no') }}</x-table.th>
                    <x-table.th>{{ __('bank.field.is_active') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($accounts as $account)
                <tr>
                    <td class="font-medium">{{ $account->label }}</td>
                    <td class="font-mono text-sm">{{ $account->iban }}</td>
                    <td>{{ $account->bic ?? '—' }}</td>
                    <td>{{ $account->datev_account_no ?? '—' }}</td>
                    <td>
                        @if ($account->is_active)
                            <x-status-badge tone="success" :label="__('bank.field.is_active')" />
                        @else
                            <x-status-badge tone="neutral" label="—" />
                        @endif
                    </td>
                    <td class="text-right">
                        @can('update', $account)
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('finance.bank-accounts.edit', $account->sqid)" />
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('bank.empty.accounts')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection

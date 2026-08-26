{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : accounts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kontenplan (Feature 125, MVP-672). Ein bebuchtes Konto wird stillgelegt,
  nicht gelöscht — sonst zeigt die Historie auf eine Nummer ins Leere.
--}}

@extends('layouts.app')

@section('title', __('accounting.ledger.accounts.title'))
@section('nav-title', __('accounting.ledger.accounts.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.ledger.accounts.subtitle')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-icon-btn icon="add" size="sm" tone="primary"
                            data-entry-modal-trigger
                            :href="route('finance.accounting.accounts.create')"
                            :label="__('accounting.ledger.action.add_account')" />
            @endif
        </x-slot:actions>

        <x-accounting.sovereignty-note />

        @if ($canConfigure && $templates !== [])
            <x-card :title="__('accounting.template.title')" icon="library_books" :subtitle="__('accounting.template.subtitle')">
                <form method="POST" action="{{ route('finance.accounting.accounts.template') }}"
                      class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    @csrf
                    <x-select-field name="template" :label="__('accounting.template.field.template')">
                        @foreach ($templates as $code => $template)
                            <option value="{{ $code }}">{{ $template['name'] }}</option>
                        @endforeach
                    </x-select-field>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('accounting.template.action.apply') }}</button>
                </form>
                <p class="mt-2 text-xs text-muted">
                    {{ $hasAccounts ? __('accounting.template.hint_additive') : __('accounting.template.hint_first') }}
                </p>
                <p class="mt-1 text-xs text-muted">{{ __('accounting.template.disclaimer') }}</p>
            </x-card>
        @endif

        <x-filter-bar :action="route('finance.accounting.accounts.index')" :reset="route('finance.accounting.accounts.index')">
            <input type="search" name="q" value="{{ $search }}" class="input input-sm input-bordered w-56 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}">
            <select name="type" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('accounting.ledger.column.type') }}">
                <option value="">{{ __('accounting.ledger.filter.all_types') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($selectedType === $type)>{{ $type->label() }}</option>
                @endforeach
            </select>
            {{-- Schalter ans Ende der Leiste (Filterleisten-Standard). --}}
            <x-filter-toggle name="only_active" class="order-40"
                             :label="__('accounting.ledger.filter.only_active')" :checked="$onlyActive" />
        </x-filter-bar>

        @if ($taxCodes->isNotEmpty())
            {{-- Steuerkennzeichen samt UStVA-Kennziffern (MVP-688). --}}
            <x-card :title="__('accounting.filing.fields.tax_codes')" icon="tag" :subtitle="__('accounting.filing.fields.subtitle')">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.ledger.column.number') }}</th>
                            <th>{{ __('accounting.ledger.column.name') }}</th>
                            <th>{{ __('accounting.reports.column.direction') }}</th>
                            <th class="text-right">{{ __('accounting.filing.fields.column.base') }}</th>
                            <th class="text-right">{{ __('accounting.filing.fields.column.tax') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($taxCodes as $taxCode)
                        <tr class="hover">
                            <td class="font-mono">{{ $taxCode->code }}</td>
                            <td>{{ $taxCode->name }}</td>
                            <td>{{ $taxCode->direction->label() }}</td>
                            <td class="text-right font-mono">{{ $taxCode->ustva_base_field ?? '—' }}</td>
                            <td class="text-right font-mono">{{ $taxCode->ustva_tax_field ?? '—' }}</td>
                            <td class="text-right">
                                @if ($canConfigure)
                                    <x-icon-btn icon="edit" size="xs" tone="ghost"
                                                data-entry-modal-trigger
                                                :href="route('finance.accounting.tax-codes.edit', $taxCode)"
                                                :label="__('Bearbeiten')" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('accounting.ledger.column.number') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.ledger.column.name') }}</x-table.th>
                    <th>{{ __('accounting.ledger.column.type') }}</th>
                    <th>{{ __('accounting.ledger.column.normal_balance') }}</th>
                    <th>{{ __('accounting.reports.column.euer_category') }}</th>
                    <th>{{ __('accounting.ledger.column.flags') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($accounts as $account)
                <tr class="hover {{ $account->is_active ? '' : 'opacity-60' }}">
                    <td class="font-mono">{{ $account->number }}</td>
                    <td class="font-medium">{{ $account->name }}</td>
                    <td><x-status-badge :tone="$account->type->tone()">{{ $account->type->label() }}</x-status-badge></td>
                    <td>{{ $account->normal_balance->label() }}</td>
                    <td class="text-sm">
                        @if ($account->euer_category)
                            {{ $account->euer_category->label() }}
                            @if ((float) $account->deductible_percent < 100)
                                <span class="text-xs text-muted">({{ $account->deductible_percent }} %)</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @if ($account->is_open_item)
                                <span class="badge badge-sm badge-outline">{{ __('accounting.ledger.flag.open_item') }}</span>
                            @endif
                            @if ($account->is_bank)
                                <span class="badge badge-sm badge-outline">{{ __('accounting.ledger.flag.bank') }}</span>
                            @endif
                            @if ($account->is_cash)
                                <span class="badge badge-sm badge-outline">{{ __('accounting.ledger.flag.cash') }}</span>
                            @endif
                            @if ($account->is_clearing)
                                <span class="badge badge-sm badge-outline">{{ __('accounting.ledger.flag.clearing') }}</span>
                            @endif
                            @unless ($account->is_active)
                                <span class="badge badge-sm badge-ghost">{{ __('accounting.ledger.flag.inactive') }}</span>
                            @endunless
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($canConfigure)
                                <x-icon-btn icon="edit" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.accounting.accounts.edit', $account)"
                                            :label="__('Bearbeiten')" />
                                @if ($account->is_active)
                                    <x-action-form :action="route('finance.accounting.accounts.deactivate', $account)"
                                                   method="POST"
                                                   :confirm="__('accounting.ledger.confirm.deactivate')">
                                        <x-icon-btn icon="block" size="xs" tone="ghost" type="submit"
                                                    :label="__('accounting.ledger.action.deactivate')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="account_tree" :title="__('accounting.ledger.empty.accounts')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$accounts" standing />
    </x-index-page>
@endsection

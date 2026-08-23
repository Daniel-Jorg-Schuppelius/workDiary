{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : open-items.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Offene Posten (Feature 125, MVP-674). Der Posten ist eine Projektion der
  Festbuchung — deshalb führt jede Zeile zurück auf ihre Buchung.
--}}

@extends('layouts.app')

@section('title', __('accounting.open_items.title'))
@section('nav-title', __('accounting.open_items.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.open_items.subtitle')">
        <div role="tablist" class="tabs tabs-box w-fit">
            @foreach (\App\Enums\Finance\OpenItemDirection::cases() as $tab)
                <a role="tab" class="tab {{ $direction === $tab ? 'tab-active' : '' }}"
                   href="{{ route('finance.accounting.open-items.index', ['direction' => $tab->value]) }}">
                    {{ $tab->label() }}
                </a>
            @endforeach
        </div>

        <div class="grid gap-3 sm:grid-cols-5">
            @foreach (['not_due', 'd30', 'd60', 'd90', 'd90plus'] as $bucket)
                <x-kpi-tile :label="__('accounting.open_items.bucket.' . $bucket)" :value="$buckets[$bucket]" />
            @endforeach
        </div>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.document_reference') }}</th>
                    <th>{{ __('accounting.open_items.column.counterparty') }}</th>
                    <th>{{ __('accounting.ledger.column.document_on') }}</th>
                    <th>{{ __('accounting.open_items.column.due_date') }}</th>
                    <th class="text-right">{{ __('accounting.open_items.column.original') }}</th>
                    <th class="text-right">{{ __('accounting.open_items.column.open') }}</th>
                    <th>{{ __('accounting.ledger.column.status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                <tr class="hover">
                    <td class="font-medium">{{ $item->document_reference ?? '—' }}</td>
                    <td>{{ $item->counterparty?->name ?? '—' }}</td>
                    <td>{{ $item->document_date->fdate() }}</td>
                    <td>
                        {{ $item->due_date?->fdate() ?? '—' }}
                        @if (($item->ageInDays() ?? 0) > 0)
                            <span class="ml-1 badge badge-xs badge-error">{{ __('accounting.open_items.overdue_days', ['days' => $item->ageInDays()]) }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ $item->original_amount?->format() }}</td>
                    <td class="text-right font-mono">{{ $item->open_amount?->format() }}</td>
                    <td><x-status-badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="menu_book" size="xs" tone="ghost"
                                        :href="route('finance.accounting.journal.show', $item->entry)"
                                        :label="__('accounting.open_items.action.show_entry')" />
                            @if ($canPost && $item->status->isOpen())
                                <x-icon-btn icon="playlist_add_check" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.accounting.open-items.settle-form', $item)"
                                            :label="__('accounting.open_items.action.settle')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="account_balance_wallet" :title="__('accounting.open_items.empty')" /></td></tr>
            @endforelse
        </x-table>

        <x-pagination :paginator="$items" standing />
    </x-index-page>
@endsection

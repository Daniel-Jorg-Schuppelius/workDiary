{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : journal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsjournal (Feature 125, MVP-672). Die Liste folgt dem globalen
  Header-Zeitraum; Festbuchungen sind hier Nachweis, nicht Arbeitsvorrat.
--}}

@extends('layouts.app')

@section('title', __('accounting.ledger.journal.title'))
@section('nav-title', __('accounting.ledger.journal.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.ledger.journal.subtitle')">
        <x-slot:actions>
            @if ($canPrepare)
                <x-icon-btn icon="add" size="sm" tone="primary"
                            data-entry-modal-trigger
                            :href="route('finance.accounting.journal.create')"
                            :label="__('accounting.ledger.action.add_entry')" />
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('finance.accounting.journal.index')" :reset="route('finance.accounting.journal.index')">
            <input type="search" name="q" value="{{ $search }}" class="input input-sm input-bordered w-56 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}">
            <select name="status" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('accounting.ledger.column.status') }}">
                <option value="">{{ __('accounting.ledger.filter.all_states') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.journal_no') }}</th>
                    <th>{{ __('accounting.ledger.column.booked_on') }}</th>
                    <th>{{ __('accounting.ledger.column.memo') }}</th>
                    <th>{{ __('accounting.ledger.column.accounts') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                    <th>{{ __('accounting.ledger.column.status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($entries as $entry)
                <tr class="hover">
                    <td class="font-mono">{{ $entry->journal_no ?? '—' }}</td>
                    <td>{{ $entry->booked_on->fdate() }}</td>
                    <td class="font-medium">{{ $entry->memo }}</td>
                    <td class="text-xs text-base-content/70">
                        {{ $entry->lines->map(fn ($line) => $line->account?->number)->filter()->implode(' / ') }}
                    </td>
                    <td class="text-right font-mono">{{ $entry->debitTotal()->format() }}</td>
                    <td><x-status-badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('finance.accounting.journal.show', $entry)"
                                        :label="__('Anzeigen')" />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="menu_book" :title="__('accounting.ledger.empty.entries')" /></td></tr>
            @endforelse
        </x-table>

        <x-pagination :paginator="$entries" standing />
    </x-index-page>
@endsection

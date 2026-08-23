{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : account-ledger.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kontenblatt (Feature 125, MVP-676). Jede Zeile führt zur Buchung — eine
  Zahl ohne Weg zum Beleg ist im Zweifel unbrauchbar.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.account_ledger.title'))
@section('nav-title', __('accounting.reports.card.account_ledger.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            @if ($selected)
                <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                            :href="route('reports.accounting.account-ledger', ['account' => $selected->sqid, 'export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.account-ledger', ['account' => $selected->sqid, 'export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.account-ledger', ['account' => $selected->sqid, 'export' => 'pdf'])" :label="__('PDF')" />
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('reports.accounting.account-ledger')" :reset="route('reports.accounting.account-ledger')">
            <select name="account" class="select select-sm select-bordered w-72 shrink-0" aria-label="{{ __('accounting.ledger.column.account') }}">
                @foreach ($accounts as $account)
                    <option value="{{ $account->sqid }}" @selected($selected?->id === $account->id)>{{ $account->displayLabel() }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('accounting.reports.column.opening')" :value="$opening" />
            <x-kpi-tile :label="__('accounting.reports.column.closing')" :value="$closing" />
        </div>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.booked_on') }}</th>
                    <th>{{ __('accounting.ledger.column.journal_no') }}</th>
                    <th>{{ __('accounting.ledger.column.memo') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.debit') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.credit') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($lines as $line)
                <tr class="hover">
                    <td>{{ $line->entry?->booked_on->fdate() }}</td>
                    <td class="font-mono">{{ $line->entry?->journal_no }}</td>
                    <td>{{ $line->entry?->memo }}</td>
                    <td class="text-right font-mono">{{ $line->debit?->getAmount() }}</td>
                    <td class="text-right font-mono">{{ $line->credit?->getAmount() }}</td>
                    <td class="text-right">
                        @if ($line->entry)
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('finance.accounting.journal.show', $line->entry)"
                                        :label="__('Anzeigen')" />
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="menu_book" :title="__('accounting.reports.empty')" /></td></tr>
            @endforelse
        </x-table>

        @if ($lines instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <x-pagination :paginator="$lines" standing />
        @endif
    </x-index-page>
@endsection

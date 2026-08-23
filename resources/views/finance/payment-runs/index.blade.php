{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zahlläufe (Feature 120, MVP-609): Sammelüberweisungen und Sammeleinzüge.
--}}

@extends('layouts.app')

@section('title', __('sepa.title'))
@section('nav-title', __('sepa.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('sepa.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="playlist_add" tone="primary" size="sm"
                        :href="route('finance.payment-runs.proposals')"
                        show-label>{{ __('sepa.action.proposal') }}</x-icon-btn>
        </x-slot:actions>

        @unless ($formatsAvailable)
            {{-- Der Lauf lässt sich ohne das Formatpaket zusammenstellen; nur
                 die Datei entsteht nicht. Das ist ehrlicher als die Seite ganz
                 auszublenden. --}}
            <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3 text-sm">
                {{ \App\Services\Finance\FinancialFormatsSupport::unavailableMessage('sepa.error.unavailable') }}
            </div>
        @endunless

        <x-table scroll="flex" :pin-rows="true" :zebra="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('sepa.column.label') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.column.kind') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.column.account') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('sepa.column.execution_date') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('sepa.column.positions') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('sepa.column.total') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($runs as $run)
                <tr class="hover">
                    <td class="font-medium">{{ $run->label ?: ($run->message_id ?? '—') }}</td>
                    <td>{{ $run->kind->label() }}</td>
                    <td>{{ $run->bankAccount?->label ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ optional($run->execution_date)->fdate() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $run->items_count }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $run->total, 2, withThousandsSeparator: true) }}</td>
                    <td><x-status-badge :tone="$run->isExported() ? 'success' : ($run->isReleased() ? 'info' : 'neutral')" outline>{{ $run->status->label() }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('finance.payment-runs.show', $run)"
                                        :label="__('sepa.action.show')" />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="account_balance" :title="__('sepa.empty')" /></td></tr>
            @endforelse
        </x-table>

        <x-pagination :paginator="$runs" standing />
    </x-index-page>
@endsection

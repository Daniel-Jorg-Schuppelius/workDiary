{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : suggestions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kontierungsvorschläge für Bankumsätze ohne Beleg (Feature 207, MVP-1660).
  Gleichartige offene Umsätze stehen gebündelt mit der Vorschau ihrer
  Buchungen; eine Bestätigung bucht alle angezeigten. Abweichende Beträge und
  Vorschläge mit Hinweis stehen einzeln und öffnen den vorbelegten Dialog.
  Vorgeschlagen wird nur, was die Organisation selbst schon so gebucht hat.
--}}

@extends('layouts.app')

@section('title', __('accounting.suggestions.title'))
@section('nav-title', __('accounting.suggestions.title'))

@php
    $money = static fn ($value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 2, withThousandsSeparator: true);
@endphp

@section('content')
    <x-index-page :subtitle="__('accounting.suggestions.subtitle')"
                  back-route="finance.accounting.inbox.index" :back-label="__('accounting.inbox.title')">
        <x-accounting.sovereignty-note />

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('accounting.suggestions.kpi.groups')" :value="count($overview['groups'])" href="#suggestion-groups" />
            <x-kpi-tile :label="__('accounting.suggestions.kpi.singles')" :value="count($overview['singles'])" href="#suggestion-singles" />
            {{-- Offen ohne Vorschlag: die Arbeit liegt im Zahlungsabgleich. --}}
            <x-kpi-tile :label="__('accounting.suggestions.kpi.without')" :value="$overview['without']" :href="route('finance.reconciliation.index')" />
        </div>

        <p class="text-sm text-muted">{{ __('accounting.suggestions.hint') }}</p>
        <x-accounting.four-eyes-note />

        <div id="suggestion-groups" class="flex flex-col gap-4 scroll-mt-4">
        @forelse ($overview['groups'] as $group)
            @php($suggestion = $group['suggestion'])
            <x-card :title="$suggestion->account->displayLabel() . ($suggestion->taxCode ? ' · ' . $suggestion->taxCode->code : '')"
                    icon="lightbulb" :subtitle="$suggestion->reason()">
                <x-slot:actions>
                    <x-status-badge :tone="$suggestion->strengthTone()">{{ __('accounting.suggestions.strength.' . $suggestion->strength()) }}</x-status-badge>
                    <x-status-badge tone="neutral" outline>{{ $suggestion->basis->label() }}</x-status-badge>
                </x-slot:actions>

                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.suggestions.column.date') }}</th>
                            <th>{{ __('accounting.suggestions.column.counterparty') }}</th>
                            <th>{{ __('accounting.suggestions.column.booking') }}</th>
                            <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($group['items'] as $item)
                        @php($transaction = $item['transaction'])
                        <tr class="hover">
                            <td>{{ $transaction->booking_date->fdate() }}</td>
                            <td class="text-sm">
                                {{ $transaction->counterparty_name ?? '—' }}
                                @if ($transaction->purpose)
                                    <div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($transaction->purpose, 70) }}</div>
                                @endif
                            </td>
                            {{-- Vorschau der Buchung: Geldseite folgt dem Umsatz, Sachkonto mit Kennzeichen und Text. --}}
                            <td class="text-xs text-base-content/70">
                                {{ $transaction->isCredit()
                                    ? __('accounting.suggestions.preview.credit', ['account' => $suggestion->account->number])
                                    : __('accounting.suggestions.preview.debit', ['account' => $suggestion->account->number]) }}
                                @if ($suggestion->taxCode) · {{ $suggestion->taxCode->code }}@endif
                                · {{ $suggestion->note }}
                            </td>
                            <td class="text-right font-mono">{{ $transaction->isCredit() ? '+' : '−' }}{{ $money($transaction->amount) }}</td>
                        </tr>
                    @endforeach
                </x-table>

                <x-action-form :action="route('finance.accounting.inbox.suggestions.confirm')" method="POST" class="mt-3 flex justify-end"
                               :confirm="trans_choice('accounting.suggestions.confirm.group', count($group['items']), ['count' => count($group['items'])])">
                    <input type="hidden" name="group" value="{{ $group['key'] }}">
                    @foreach ($group['items'] as $item)
                        <input type="hidden" name="transactions[]" value="{{ $item['transaction']->sqid }}">
                    @endforeach
                    <x-button type="submit" size="sm">
                        {{ trans_choice('accounting.suggestions.action.confirm_group', count($group['items']), ['count' => count($group['items'])]) }}
                    </x-button>
                </x-action-form>
            </x-card>
        @empty
            <x-empty-state icon="lightbulb" :title="__('accounting.suggestions.empty')" />
        @endforelse
        </div>

        @if ($overview['singles'] !== [])
            <x-card id="suggestion-singles" :title="__('accounting.suggestions.singles.title')" icon="rule" :subtitle="__('accounting.suggestions.singles.subtitle')">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.suggestions.column.date') }}</th>
                            <th>{{ __('accounting.suggestions.column.counterparty') }}</th>
                            <th>{{ __('accounting.suggestions.column.suggestion') }}</th>
                            <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($overview['singles'] as $item)
                        @php($transaction = $item['transaction'])
                        @php($suggestion = $item['suggestion'])
                        <tr class="hover">
                            <td>{{ $transaction->booking_date->fdate() }}</td>
                            <td class="text-sm">{{ $transaction->counterparty_name ?? '—' }}</td>
                            <td class="text-xs">
                                <div>{{ $suggestion->account->displayLabel() }}@if ($suggestion->taxCode) · {{ $suggestion->taxCode->code }}@endif</div>
                                <div class="text-muted">{{ $suggestion->reason() }}</div>
                                @if ($suggestion->deviates)
                                    <div class="text-warning">{{ __('accounting.suggestions.deviates', ['last' => $money($suggestion->lastAmount)]) }}</div>
                                @endif
                                @if ($suggestion->warning !== null)
                                    <div class="text-warning">{{ __('accounting.suggestions.warning.' . $suggestion->warning) }}</div>
                                @endif
                            </td>
                            <td class="text-right font-mono">{{ $transaction->isCredit() ? '+' : '−' }}{{ $money($transaction->amount) }}</td>
                            <td class="text-right">
                                <x-icon-btn icon="account_balance" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.accounting.inbox.bank-direct.create', $transaction->sqid)"
                                            :label="__('accounting.bank_direct.action.post')" />
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    </x-index-page>
@endsection

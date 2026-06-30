{{--
  Created on   : Sat Jun 13 2026
  License      : AGPL-3.0-or-later

  Auszug-Detail (Feature 045, Priorität 3): Transaktionsliste mit
  match_status-Badge, Vorschlägen (inline „Bestätigen") und reversiblen
  Zuordnungen. Bankumsätze sind NIE editierbar — nur ihr Zuordnungsstatus.
--}}

@extends('layouts.app')

@section('title', __('bank.title.statement'))
@section('nav-title', __('bank.title.statement'))

@php
    $canReconcile = auth()->user()?->can('reconcile', $statement->transactions->first() ?? new \App\Models\Finance\BankTransaction()) ?? false;
@endphp

@section('content')
    <x-index-page :subtitle="$statement->bankAccount?->label">
        <x-slot:actions>
            @can('download', $statement)
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('finance.reconciliation.download', $statement->sqid)"
                            show-label>{{ __('bank.action.download') }}</x-icon-btn>
            @endcan
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('finance.reconciliation.index')"
                        show-label>{{ __('bank.title.index') }}</x-icon-btn>
        </x-slot:actions>

        <x-card class="mb-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <div class="text-base-content/60">{{ __('bank.field.format') }}</div>
                    <x-status-badge :tone="$statement->source_format->tone()" :label="$statement->source_format->label()" />
                </div>
                <div>
                    <div class="text-base-content/60">{{ __('bank.field.balance_check') }}</div>
                    <x-status-badge :tone="$statement->balance_check->tone()" :label="$statement->balance_check->label()" />
                </div>
                <div>
                    <div class="text-base-content/60">{{ __('bank.field.opening_balance') }}</div>
                    <div>{{ $statement->opening_balance !== null ? number_format((float) $statement->opening_balance, 2, ',', '.') : '—' }}</div>
                </div>
                <div>
                    <div class="text-base-content/60">{{ __('bank.field.closing_balance') }}</div>
                    <div>{{ $statement->closing_balance !== null ? number_format((float) $statement->closing_balance, 2, ',', '.') : '—' }}</div>
                </div>
            </div>
        </x-card>

        <div class="space-y-3">
            @forelse ($statement->transactions as $transaction)
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="font-medium">
                                {{ $transaction->booking_date->format('d.m.Y') }} ·
                                <span class="{{ $transaction->isCredit() ? 'text-success' : 'text-base-content' }}">
                                    {{ $transaction->isCredit() ? '+' : '−' }}{{ number_format((float) $transaction->amount, 2, ',', '.') }} {{ $transaction->currency }}
                                </span>
                            </div>
                            <div class="text-sm text-base-content/70">
                                {{ $transaction->counterparty_name ?? '—' }}
                                @if ($transaction->purpose)
                                    · <span class="text-base-content/50">{{ \Illuminate\Support\Str::limit($transaction->purpose, 80) }}</span>
                                @endif
                            </div>
                        </div>
                        <x-status-badge :tone="$transaction->match_status->tone()" :label="$transaction->match_status->label()" />
                    </div>

                    {{-- Bestätigte Zuordnungen (reversibel) --}}
                    @if ($transaction->allocations->isNotEmpty())
                        <div class="mt-3 border-t border-base-200 pt-2 space-y-1">
                            @foreach ($transaction->allocations as $allocation)
                                <div class="flex items-center justify-between text-sm">
                                    <span>
                                        <x-status-badge size="xs" :tone="$allocation->kind->tone()" :label="$allocation->kind->label()" />
                                        {{ \App\Support\EntityType::label($allocation->allocatable_type) }} #{{ $allocation->allocatable_id }}
                                        · {{ number_format((float) $allocation->amount, 2, ',', '.') }}
                                    </span>
                                    @if ($canReconcile)
                                        <x-action-form :action="route('finance.reconciliation.unmatch', $allocation->sqid)" method="DELETE"
                                              :confirm="__('bank.action.unmatch') . '?'">
                                            <x-icon-btn icon="link_off" tone="error" size="xs" type="submit"
                                                        show-label>{{ __('bank.action.unmatch') }}</x-icon-btn>
                                        </x-action-form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Vorschläge für offene Umsätze --}}
                    @if ($canReconcile && $transaction->match_status->isOpen())
                        @php $txSuggestions = $suggestions[$transaction->id] ?? []; @endphp
                        <div class="mt-3 border-t border-base-200 pt-2">
                            @forelse ($txSuggestions as $suggestion)
                                @php $target = $suggestion['target']; @endphp
                                <form method="POST" action="{{ route('finance.reconciliation.confirm', $transaction->sqid) }}"
                                      class="flex flex-wrap items-center justify-between gap-2 py-1">
                                    @csrf
                                    <input type="hidden" name="allocations[0][type]" value="{{ $target instanceof \App\Models\Invoice ? 'invoice' : 'expense' }}">
                                    <input type="hidden" name="allocations[0][id]" value="{{ $target->sqid }}">
                                    <input type="hidden" name="allocations[0][amount]" value="{{ $transaction->amount }}">
                                    <div class="text-sm">
                                        <span class="font-medium">
                                            {{ $target instanceof \App\Models\Invoice ? $target->number : (__('bank.title.menu') . ' #' . $target->id) }}
                                        </span>
                                        · {{ number_format($suggestion['open_amount'], 2, ',', '.') }}
                                        @foreach ($suggestion['reasons'] as $reason)
                                            <span class="badge badge-xs badge-ghost">{{ __('bank.reason.' . $reason) }}</span>
                                        @endforeach
                                    </div>
                                    <x-icon-btn icon="check" tone="success" size="xs" type="submit"
                                                show-label>{{ __('bank.action.confirm') }}</x-icon-btn>
                                </form>
                            @empty
                                <div class="text-sm text-base-content/60">{{ __('bank.empty.suggestions') }}</div>
                            @endforelse

                            <div class="flex gap-2 mt-2">
                                <form method="POST" action="{{ route('finance.reconciliation.ignore', $transaction->sqid) }}">
                                    @csrf
                                    <x-icon-btn icon="block" tone="ghost" size="xs" type="submit"
                                                show-label>{{ __('bank.action.ignore') }}</x-icon-btn>
                                </form>
                                <form method="POST" action="{{ route('finance.reconciliation.unassignable', $transaction->sqid) }}">
                                    @csrf
                                    <x-icon-btn icon="help" tone="ghost" size="xs" type="submit"
                                                show-label>{{ __('bank.action.unassignable') }}</x-icon-btn>
                                </form>
                            </div>
                        </div>
                    @endif
                </x-card>
            @empty
                <div class="text-center text-base-content/60 py-6">{{ __('bank.empty.transactions') }}</div>
            @endforelse
        </div>
    </x-index-page>
@endsection

{{--
  Created on   : Sat Jun 13 2026
  License      : AGPL-3.0-or-later

  Auszug-Detail (Feature 045, Priorität 3): Transaktionsliste mit
  match_status-Badge, Vorschlägen (inline „Bestätigen") und reversiblen
  Zuordnungen. Bankumsätze sind NIE editierbar — nur ihr Zuordnungsstatus.
  MVP-334: Sammelbuchungen lassen sich über Mehrfachauswahl + Teilbeträge in
  Einzelpositionen auflösen; Lastschrift-Rückläufer werden erkannt und über
  eine GoBD-konforme Kompensation der Original-Zuordnung verarbeitet.
  Toolkit-Folgepaket 2: trägt der Umsatz eine TxDtls-Detail-Liste
  (Sammelbuchung), wird eine VORBEFÜLLTE Aufteilung angezeigt (je Detail eine
  Zeile mit Betrag + gematchtem Posten); die Bestätigung läuft unverändert
  über den confirm-Mehrfach-Pfad. Sammel-Rücklastschriften bieten die
  Kompensation je Einzeltransaktion an.
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
                    <div>{{ $statement->opening_balance !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $statement->opening_balance, 2, withThousandsSeparator: true) : '—' }}</div>
                </div>
                <div>
                    <div class="text-base-content/60">{{ __('bank.field.closing_balance') }}</div>
                    <div>{{ $statement->closing_balance !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $statement->closing_balance, 2, withThousandsSeparator: true) : '—' }}</div>
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
                                    {{ $transaction->isCredit() ? '+' : '−' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transaction->amount, 2, withThousandsSeparator: true) }} {{ $transaction->currency->value }}
                                </span>
                            </div>
                            <div class="text-sm text-base-content/70">
                                {{ $transaction->counterparty_name ?? '—' }}
                                @if ($transaction->purpose)
                                    · <span class="text-base-content/50">{{ \Illuminate\Support\Str::limit($transaction->purpose, 80) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($transaction->isReturnCandidate())
                                {{-- Rückläufer-Kennzeichen (MVP-334): RVSL bzw. ISO-Rückgabegrund. --}}
                                <span class="badge badge-error badge-outline badge-sm">
                                    {{ __('bank.return.badge') }}@if ($transaction->return_reason) · {{ $transaction->return_reason }}@endif
                                </span>
                            @endif
                            <x-status-badge :tone="$transaction->match_status->tone()" :label="$transaction->match_status->label()" />
                        </div>
                    </div>

                    {{-- Bestätigte Zuordnungen (reversibel) --}}
                    @if ($transaction->allocations->isNotEmpty())
                        <div class="mt-3 border-t border-base-200 pt-2 space-y-1">
                            @foreach ($transaction->allocations as $allocation)
                                <div class="flex items-center justify-between text-sm">
                                    <span>
                                        <x-status-badge size="xs" :tone="$allocation->kind->tone()" :label="$allocation->kind->label()" />
                                        {{ \App\Support\EntityType::label($allocation->allocatable_type) }} #{{ $allocation->allocatable_id }}
                                        · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $allocation->amount, 2, withThousandsSeparator: true) }}
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

                    {{-- Vorschläge für offene Umsätze — Mehrfachauswahl mit Teilbeträgen
                         (MVP-334: Sammelbuchung in Einzelpositionen auflösen). --}}
                    @if ($canReconcile && $transaction->match_status->isOpen())
                        @php
                            $txSuggestions = $suggestions[$transaction->id] ?? [];
                            $txSplit = $splitSuggestions[$transaction->id] ?? [];
                            $txDetailOrigins = $detailReturnOrigins[$transaction->id] ?? [];
                        @endphp
                        <div class="mt-3 border-t border-base-200 pt-2">
                            @if ($txSplit !== [])
                                {{-- Sammelbuchung (Toolkit-Folgepaket 2): vorbefüllte Aufteilung
                                     je TransactionDetail; nicht gematchte Details bleiben
                                     editierbare Zeilen. Bestätigung über den EXISTIERENDEN
                                     confirm-Mehrfach-Pfad. --}}
                                @php
                                    $splitRows = [];
                                    foreach ($txSplit as $splitRow) {
                                        $splitTarget = $splitRow['suggestion']['target'] ?? null;
                                        $splitRows[$splitRow['index']] = [
                                            'picked' => $splitTarget !== null,
                                            'target' => $splitTarget !== null
                                                ? (($splitTarget instanceof \App\Models\Invoice ? 'invoice:' : 'expense:') . $splitTarget->sqid)
                                                : '',
                                        ];
                                    }
                                @endphp
                                <div class="text-sm font-medium mb-1">
                                    {{ __('bank.split.title') }}
                                    <span class="badge badge-ghost badge-xs">{{ count($txSplit) }}</span>
                                </div>
                                {{-- Logik in Alpine.data("reconciliationSplit") (components.js) — CSP-Build-konform. --}}
                                <form method="POST" action="{{ route('finance.reconciliation.confirm', $transaction->sqid) }}"
                                      x-data="reconciliationSplit"
                                      data-rows="{{ json_encode($splitRows) }}">
                                    @csrf
                                    <div class="space-y-1">
                                        @foreach ($txSplit as $splitRow)
                                            @php
                                                $index = $splitRow['index'];
                                                $detail = $splitRow['detail'];
                                                $detailSigned = (float) ($detail['amount'] ?? 0);
                                            @endphp
                                            <div class="flex flex-wrap items-center gap-2 py-1">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" class="checkbox checkbox-xs"
                                                           x-model="rows[{{ $index }}].picked">
                                                    <span class="text-sm">
                                                        <span class="font-medium {{ $detailSigned >= 0 ? 'text-success' : 'text-base-content' }}">
                                                            {{ $detailSigned >= 0 ? '+' : '−' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(abs($detailSigned), 2, withThousandsSeparator: true) }}
                                                        </span>
                                                        @if (! empty($detail['counterparty_name']))
                                                            · {{ $detail['counterparty_name'] }}
                                                        @endif
                                                        @if (! empty($detail['end_to_end_id']))
                                                            · <span class="text-base-content/50">{{ $detail['end_to_end_id'] }}</span>
                                                        @endif
                                                        @if ($splitRow['suggestion'] !== null)
                                                            @foreach ($splitRow['suggestion']['reasons'] as $reason)
                                                                <span class="badge badge-xs badge-ghost">{{ __('bank.reason.' . $reason) }}</span>
                                                            @endforeach
                                                        @else
                                                            <span class="badge badge-xs badge-warning badge-outline">{{ __('bank.split.no_match') }}</span>
                                                        @endif
                                                    </span>
                                                </label>
                                                <select class="select select-bordered select-xs w-56"
                                                        x-model="rows[{{ $index }}].target"
                                                        :disabled="unpicked({{ $index }})"
                                                        aria-label="{{ __('bank.split.target') }}">
                                                    <option value="">{{ __('bank.split.target_placeholder') }}</option>
                                                    @foreach ($splitTargets as $option)
                                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" name="allocations[{{ $index }}][type]"
                                                       :value="allocType({{ $index }})"
                                                       :disabled="idle({{ $index }})">
                                                <input type="hidden" name="allocations[{{ $index }}][id]"
                                                       :value="allocId({{ $index }})"
                                                       :disabled="idle({{ $index }})">
                                                <input type="number" step="0.01" min="0.01"
                                                       name="allocations[{{ $index }}][amount]"
                                                       value="{{ number_format(abs($detailSigned), 2, '.', '') }}"
                                                       :disabled="idle({{ $index }})"
                                                       class="input input-bordered input-xs w-28"
                                                       aria-label="{{ __('bank.field.amount') }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-end mt-1">
                                        <x-icon-btn icon="check" tone="success" size="xs" type="submit"
                                                    show-label>{{ __('bank.action.confirm_selected') }}</x-icon-btn>
                                    </div>
                                </form>
                            @elseif ($txSuggestions !== [])
                                {{-- Logik in Alpine.data("reconciliationPick") (components.js) — CSP-Build-konform. --}}
                                <form method="POST" action="{{ route('finance.reconciliation.confirm', $transaction->sqid) }}"
                                      x-data="reconciliationPick">
                                    @csrf
                                    <div class="space-y-1">
                                        @foreach ($txSuggestions as $index => $suggestion)
                                            @php
                                                $target = $suggestion['target'];
                                                // Kundenkonto (Feature 098) als dritter Zieltyp neben Invoice/Expense.
                                                $isAccount = $target instanceof \App\Models\Billing\CustomerBillingAgreement;
                                                $targetLabel = match (true) {
                                                    $target instanceof \App\Models\Invoice => $target->number,
                                                    $isAccount => __('customer-billing.panel_title') . ': ' . ($target->customer?->name ?? '#' . $target->customer_id),
                                                    default => __('bank.title.menu') . ' #' . $target->id,
                                                };
                                            @endphp
                                            <div class="flex flex-wrap items-center gap-2 py-1">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" class="checkbox checkbox-xs"
                                                           x-model="picked[{{ $index }}]">
                                                    <span class="text-sm">
                                                        <span class="font-medium">{{ $targetLabel }}</span>
                                                        · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($suggestion['open_amount'], 2, withThousandsSeparator: true) }}
                                                        @foreach ($suggestion['reasons'] as $reason)
                                                            <span class="badge badge-xs badge-ghost">{{ __('bank.reason.' . $reason) }}</span>
                                                        @endforeach
                                                    </span>
                                                </label>
                                                <input type="hidden" name="allocations[{{ $index }}][type]"
                                                       value="{{ $target instanceof \App\Models\Invoice ? 'invoice' : ($isAccount ? 'account' : 'expense') }}"
                                                       :disabled="unpicked({{ $index }})">
                                                <input type="hidden" name="allocations[{{ $index }}][id]" value="{{ $target->sqid }}"
                                                       :disabled="unpicked({{ $index }})">
                                                <input type="number" step="0.01" min="0.01"
                                                       name="allocations[{{ $index }}][amount]"
                                                       value="{{ number_format($isAccount ? (float) $transaction->amount : min((float) $transaction->amount, $suggestion['open_amount']), 2, '.', '') }}"
                                                       :disabled="unpicked({{ $index }})"
                                                       class="input input-bordered input-xs w-28"
                                                       aria-label="{{ __('bank.field.amount') }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-end mt-1">
                                        <x-icon-btn icon="check" tone="success" size="xs" type="submit"
                                                    show-label>{{ __('bank.action.confirm_selected') }}</x-icon-btn>
                                    </div>
                                </form>
                            @elseif ($txDetailOrigins === [])
                                <div class="text-sm text-base-content/60">{{ __('bank.empty.suggestions') }}</div>
                            @endif

                            {{-- Sammel-Rücklastschrift (Toolkit-Folgepaket 2): Kompensation je
                                 Einzeltransaktion — jede Zeile speist den bestehenden
                                 processReturn-Pfad je Original-Zuordnung. --}}
                            @if ($txDetailOrigins !== [])
                                <div class="mt-3 border-t border-base-200 pt-2">
                                    <div class="text-sm font-medium mb-1">{{ __('bank.split.return_title') }}</div>
                                    @foreach ($txDetailOrigins as $detailIndex => $origins)
                                        @php
                                            $detail = $transaction->transactionDetails()[$detailIndex] ?? [];
                                            $detailSigned = (float) ($detail['amount'] ?? 0);
                                        @endphp
                                        <form method="POST" action="{{ route('finance.reconciliation.return', $transaction->sqid) }}" class="mb-2">
                                            @csrf
                                            <div class="text-xs text-base-content/60 mb-1">
                                                {{ $detailSigned >= 0 ? '+' : '−' }}{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(abs($detailSigned), 2, withThousandsSeparator: true) }}
                                                @if (! empty($detail['counterparty_name']))
                                                    · {{ $detail['counterparty_name'] }}
                                                @endif
                                                @if (! empty($detail['end_to_end_id']))
                                                    · {{ $detail['end_to_end_id'] }}
                                                @endif
                                                @if (! empty($detail['return_reason']))
                                                    · <span class="badge badge-error badge-outline badge-xs">{{ $detail['return_reason'] }}</span>
                                                @endif
                                            </div>
                                            <div class="space-y-1">
                                                @foreach ($origins as $originIndex => $origin)
                                                    @php $allocation = $origin['allocation']; @endphp
                                                    <label class="flex flex-wrap items-center gap-2 text-sm cursor-pointer">
                                                        <input type="radio" name="allocation" value="{{ $allocation->sqid }}"
                                                               class="radio radio-xs" @checked($originIndex === 0)>
                                                        <x-status-badge size="xs" :tone="$allocation->kind->tone()" :label="$allocation->kind->label()" />
                                                        {{ \App\Support\EntityType::label($allocation->allocatable_type) }} #{{ $allocation->allocatable_id }}
                                                        · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $allocation->amount, 2, withThousandsSeparator: true) }}
                                                        @foreach ($origin['reasons'] as $reason)
                                                            <span class="badge badge-xs badge-ghost">{{ __('bank.return.reason.' . $reason) }}</span>
                                                        @endforeach
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="flex flex-wrap items-center justify-end gap-2 mt-1">
                                                <input type="text" name="reason" maxlength="255"
                                                       value="{{ $detail['return_reason'] ?? $transaction->return_reason }}"
                                                       placeholder="{{ __('bank.return.reason_placeholder') }}"
                                                       class="input input-bordered input-xs w-48">
                                                <x-icon-btn icon="undo" tone="error" size="xs" type="submit"
                                                            show-label>{{ __('bank.return.action') }}</x-icon-btn>
                                            </div>
                                        </form>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Lastschrift-Rückläufer (MVP-334): Original-Zuordnung kompensieren. --}}
                            @php $txOrigins = $returnOrigins[$transaction->id] ?? []; @endphp
                            @if ($txOrigins !== [])
                                <div class="mt-3 border-t border-base-200 pt-2">
                                    <div class="text-sm font-medium mb-1">{{ __('bank.return.title') }}</div>
                                    <form method="POST" action="{{ route('finance.reconciliation.return', $transaction->sqid) }}">
                                        @csrf
                                        <div class="space-y-1">
                                            @foreach ($txOrigins as $originIndex => $origin)
                                                @php $allocation = $origin['allocation']; @endphp
                                                <label class="flex flex-wrap items-center gap-2 text-sm cursor-pointer">
                                                    <input type="radio" name="allocation" value="{{ $allocation->sqid }}"
                                                           class="radio radio-xs" @checked($originIndex === 0)>
                                                    <x-status-badge size="xs" :tone="$allocation->kind->tone()" :label="$allocation->kind->label()" />
                                                    {{ \App\Support\EntityType::label($allocation->allocatable_type) }} #{{ $allocation->allocatable_id }}
                                                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $allocation->amount, 2, withThousandsSeparator: true) }}
                                                    @foreach ($origin['reasons'] as $reason)
                                                        <span class="badge badge-xs badge-ghost">{{ __('bank.return.reason.' . $reason) }}</span>
                                                    @endforeach
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2 mt-1">
                                            <input type="text" name="reason" maxlength="255"
                                                   value="{{ $transaction->return_reason }}"
                                                   placeholder="{{ __('bank.return.reason_placeholder') }}"
                                                   class="input input-bordered input-xs w-48">
                                            <x-icon-btn icon="undo" tone="error" size="xs" type="submit"
                                                        show-label>{{ __('bank.return.action') }}</x-icon-btn>
                                        </div>
                                    </form>
                                </div>
                            @endif

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

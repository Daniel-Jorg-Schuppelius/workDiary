{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragsforderung (MVP-850): Positionen, Zahlungsstand, Mitteilung (PDF/Mail mit Zustellnachweis), Storno, Korrektur. --}}
@extends('layouts.app')
@section('title', $claim->number)
@section('nav-title', __('club.fees.title.claims'))
@section('content')
@php $isCancelled = $claim->status === \App\Enums\Club\ClubFeeClaimStatus::Cancelled; @endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$claim->number . ' · ' . ($claim->account?->name ?? '')" :badge="$claim->status->label()" :badgeTone="$claim->status->tone()">
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm" :href="route('club.fees.claims.pdf', $claim)" show-label>{{ __('club.fees.action.pdf') }}</x-icon-btn>
                @if ($canManage && ! $isCancelled)
                    @if ($claim->account && $claim->openAmount()->isPositive())
                        <x-icon-btn icon="payments" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.payments.create', [$claim->account, $claim])" show-label>{{ __('club.fees.action.record_payment') }}</x-icon-btn>
                    @endif
                    @if ($canDun)
                        <x-icon-btn icon="notification_important" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.fees.claims.dun.edit', $claim)" show-label>{{ __('club.fees.action.dun', ['level' => $claim->dunning_level + 1]) }}</x-icon-btn>
                    @endif
                    <x-icon-btn icon="outgoing_mail" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.claims.send.edit', $claim)" show-label>{{ __('club.fees.action.send') }}</x-icon-btn>
                    <x-icon-btn icon="difference" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.fees.claims.correction.create', $claim)" show-label>{{ __('club.fees.action.correction') }}</x-icon-btn>
                    @if ($claim->paid_amount->isZero())
                        <x-icon-btn icon="block" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.fees.claims.cancel.edit', $claim)" show-label>{{ __('club.fees.action.cancel_claim') }}</x-icon-btn>
                    @endif
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.fees.claims.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
                <x-help-button topic="club.fees" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($isCancelled)
        <div class="alert alert-warning text-sm" role="status"><x-icon name="block" /><span>{{ __('club.fees.label.cancelled_on', ['date' => $claim->cancelled_at?->orgTz()->format('d.m.Y H:i'), 'by' => $claim->cancelledBy?->name ?? '–']) }} — {{ $claim->reason }}</span></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.fees.card.items')" icon="receipt_long" :count="$claim->items->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.fees.field.position') }}</th>
                            <th>{{ __('club.field.period') }}</th>
                            <th class="text-right">{{ __('club.fees.field.amount') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($claim->items as $item)
                        <tr>
                            <td class="text-sm">{{ $item->member?->fullName() ?? __('club.fees.label.whole_account') }}</td>
                            <td class="text-sm">{{ $item->label }} <span class="text-xs text-muted">({{ \App\Enums\Club\ClubFeePositionKind::tryFrom($item->kind)?->label() ?? $item->kind }})</span></td>
                            <td class="whitespace-nowrap text-sm tabular-nums">{{ $item->period_start->format('d.m.Y') }} – {{ $item->period_end->format('d.m.Y') }}</td>
                            <td class="text-right tabular-nums">{{ $item->amount->format() }}</td>
                        </tr>
                    @endforeach
                    <x-slot:foot>
                        <tr><th colspan="3" class="text-right">{{ __('club.fees.label.total') }}</th><th class="text-right tabular-nums">{{ $claim->total->format() }}</th></tr>
                        <tr><td colspan="3" class="text-right text-sm">{{ __('club.fees.field.paid_amount') }}</td><td class="text-right tabular-nums">{{ $claim->paid_amount->format() }}</td></tr>
                        <tr><th colspan="3" class="text-right">{{ __('club.fees.field.open_amount') }}</th><th class="text-right tabular-nums">{{ $claim->openAmount()->format() }}</th></tr>
                    </x-slot:foot>
                </x-table>
            </x-card>

            @if ($claim->corrections->isNotEmpty() || $claim->correctedClaim)
                <x-card :title="__('club.fees.card.corrections')" icon="difference">
                    <ul class="space-y-1 text-sm">
                        @if ($claim->correctedClaim)
                            <li>{{ __('club.fees.label.corrects') }}: <a href="{{ route('club.fees.claims.show', $claim->correctedClaim) }}" class="link link-hover">{{ $claim->correctedClaim->number }}</a> — {{ $claim->reason }}</li>
                        @endif
                        @foreach ($claim->corrections as $correction)
                            <li><a href="{{ route('club.fees.claims.show', $correction) }}" class="link link-hover">{{ $correction->number }}</a> <span class="tabular-nums">{{ $correction->total->format() }}</span> <span class="text-xs text-muted">{{ $correction->reason }}</span> <x-status-badge :tone="$correction->status->tone()" size="xs">{{ $correction->status->label() }}</x-status-badge></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.fees.card.claim')" icon="info">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('club.fees.field.account') }}</dt><dd>@if ($claim->account)<a href="{{ route('club.fees.accounts.show', $claim->account) }}" class="link link-hover">{{ $claim->account->name }}</a>@endif</dd>
                    <dt class="text-muted">{{ __('club.fees.field.issued_on') }}</dt><dd>{{ $claim->issued_on->format('d.m.Y') }}</dd>
                    <dt class="text-muted">{{ __('club.fees.field.due_on') }}</dt><dd class="{{ $claim->isOverdue($today) ? 'text-error font-medium' : '' }}">{{ $claim->due_on->format('d.m.Y') }}</dd>
                    <dt class="text-muted">{{ __('club.field.period') }}</dt><dd>{{ $claim->period_start->format('d.m.Y') }} – {{ $claim->period_end->format('d.m.Y') }}</dd>
                    <dt class="text-muted">{{ __('club.fees.field.payment_reference') }}</dt><dd class="font-mono">{{ $claim->number }}</dd>
                    @if ($claim->run)<dt class="text-muted">{{ __('club.fees.field.run') }}</dt><dd><a href="{{ route('club.fees.runs.show', $claim->run) }}" class="link link-hover">{{ $claim->run->monthLabel() }}</a></dd>@endif
                </dl>
            </x-card>

            <x-card :title="__('club.fees.card.payments')" icon="payments" :count="$payments->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($payments as $payment)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $payment->paid_on->format('d.m.Y') }}</span>
                            <span class="text-xs text-muted">{{ $payment->method->label() }} · {{ $payment->source->label() }}@if ($payment->reference) · {{ $payment->reference }}@endif</span>
                            <span class="ml-auto tabular-nums {{ $payment->amount->isNegative() ? 'text-error' : '' }}">{{ $payment->amount->format() }}</span>
                            @if ($canManage && $payment->amount->isPositive() && ! $payment->isChargeback() && $claim->account)
                                <x-icon-btn icon="undo" tone="outline" size="xs" class="btn-warning" data-entry-modal-trigger :href="route('club.fees.payments.chargeback.edit', [$claim->account, $payment])" :label="__('club.fees.action.chargeback')" />
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.payments') }}</li>
                    @endforelse
                </ul>
                @if ($claim->collection_blocked_at)
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <x-status-badge tone="warning" size="xs" :label="__('club.fees.label.collection_blocked', ['reason' => $claim->collection_block_reason])" />
                        @if ($canManage)
                            <x-action-form :action="route('club.fees.claims.collection.unblock', $claim)" :confirm="__('club.fees.confirm.unblock_collection')" confirm-icon="lock_open" confirm-tone="primary">
                                <x-icon-btn type="submit" icon="lock_open" tone="outline" size="xs" show-label>{{ __('club.fees.action.unblock_collection') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                    </div>
                @endif
            </x-card>

            <x-card :title="__('club.fees.card.dunning')" icon="notification_important" :count="$dunnings->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($dunnings as $dunning)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ __('club.fees.label.dunning_level', ['level' => $dunning->level]) }}</span>
                            <span class="tabular-nums text-muted">{{ $dunning->issued_on->format('d.m.Y') }}</span>
                            @if ($dunning->pay_until)<span class="text-xs text-muted">{{ __('club.fees.pdf.pay_until', ['date' => $dunning->pay_until->format('d.m.Y')]) }}</span>@endif
                            @if ($dunning->fee)<span class="text-xs text-muted">{{ __('club.fees.field.dunning_fee') }} {{ $dunning->fee->format() }}</span>@endif
                            <x-icon-btn icon="picture_as_pdf" tone="ghost" size="xs" class="ml-auto" :href="route('club.fees.claims.dunning.pdf', [$claim, $dunning])" :label="__('club.fees.action.pdf')" />
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.dunnings') }}</li>
                    @endforelse
                </ul>
                @if ($claim->isDunningBlocked())
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <x-status-badge tone="warning" size="xs" :label="__('club.fees.label.dunning_blocked', ['reason' => $claim->dunning_block_reason])" />
                        @if ($canManage)
                            <x-action-form :action="route('club.fees.claims.dunning.unblock', $claim)">
                                <x-icon-btn type="submit" icon="play_circle" tone="outline" size="xs" show-label>{{ __('club.fees.action.unblock_dunning') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                    </div>
                @elseif ($canManage && ! $isCancelled && $claim->status->isOpen())
                    <div class="mt-2"><x-icon-btn icon="pause_circle" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.claims.dunning.block.edit', $claim)" show-label>{{ __('club.fees.action.block_dunning') }}</x-icon-btn></div>
                @endif
            </x-card>

            <x-card :title="__('club.fees.card.dispatches')" icon="outgoing_mail" :count="$dispatches->count()">
                <ul class="space-y-1 text-xs">
                    @forelse ($dispatches as $dispatch)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums text-muted">{{ $dispatch->created_at?->orgTz()->format('d.m.Y H:i') }}</span>
                            <span>{{ __('club.fees.channel.' . $dispatch->channel) }}</span>
                            @if ($dispatch->recipient)<span class="text-muted">{{ $dispatch->recipient }}</span>@endif
                            <x-status-badge :tone="$dispatch->status === 'sent' ? 'success' : ($dispatch->status === 'failed' ? 'error' : 'warning')" size="xs">{{ __('club.fees.dispatch.' . $dispatch->status) }}</x-status-badge>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.dispatches') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.fees.hint.dispatch') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

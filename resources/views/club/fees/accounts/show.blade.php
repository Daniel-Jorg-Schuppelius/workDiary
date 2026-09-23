{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragskonto (MVP-849): Zahlungspflichtige/r, zugeordnete Mitglieder mit Tarif und Zeitraum, Befreiungen. --}}
@extends('layouts.app')
@section('title', $account->name)
@section('nav-title', __('club.fees.title.accounts'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$account->name . ($account->customer?->number ? ' · ' . $account->customer->number : '')">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="payments" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.payments.create', $account)" show-label>{{ __('club.fees.action.record_payment') }}</x-icon-btn>
                    <x-icon-btn icon="person_add" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.fees.assignments.create', $account)" show-label>{{ __('club.fees.action.assign') }}</x-icon-btn>
                    <x-icon-btn icon="settings" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.fees.accounts.settings.edit', $account)" show-label>{{ __('club.fees.action.account_settings') }}</x-icon-btn>
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.fees.accounts.edit', $account)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.fees.accounts.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
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

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.fees.card.assignments')" icon="groups" :count="$account->assignments->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.assignments') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.fees.field.tariff') }}</th>
                            <th>{{ __('club.field.period') }}</th>
                            <th>{{ __('club.fees.field.discount') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($account->assignments as $assignment)
                        @php $active = $assignment->isActiveOn($today); @endphp
                        <tr class="align-top {{ $active ? '' : 'opacity-60' }}">
                            <td class="font-medium">
                                @if ($assignment->member)
                                    <a href="{{ route('club.members.show', $assignment->member) }}" class="link link-hover">{{ $assignment->member->fullName() }}</a>
                                    <span class="block text-xs text-muted">{{ $assignment->member->displayNo() }}@if ($assignment->member->left_on) · {{ __('club.label.left') }} {{ $assignment->member->left_on->format('d.m.Y') }}@endif</span>
                                @endif
                                @if ($assignment->needsReview())<x-status-badge tone="warning" size="xs" :label="__('club.fees.label.review_required')" :title="$assignment->review_note" />@endif
                            </td>
                            <td class="text-sm">{{ $assignment->tariff?->name }} @if ($assignment->tariff?->isFamily())<x-status-badge tone="info" size="xs" :label="$assignment->tariff->kind->label()" />@endif</td>
                            <td class="text-sm tabular-nums">{{ $assignment->valid_from->format('d.m.Y') }} – {{ $assignment->valid_to?->format('d.m.Y') ?? __('club.label.open_end') }}</td>
                            <td class="text-sm">@if ($assignment->discount_percent !== null){{ $assignment->discountPercent() }} % <span class="text-xs text-muted">{{ $assignment->discount_reason }}</span>@else–@endif</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @if ($assignment->needsReview())
                                            <x-icon-btn icon="rule" tone="outline" size="xs" class="btn-warning" data-entry-modal-trigger :href="route('club.fees.assignments.review.edit', [$account, $assignment])" :label="__('club.fees.action.review')" />
                                        @endif
                                        <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.assignments.edit', [$account, $assignment])" :label="__('club.action.edit')" />
                                        @if ($assignment->member)
                                            <x-icon-btn icon="money_off" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.exemptions.create', [$account, $assignment->member])" :label="__('club.fees.action.add_exemption')" />
                                        @endif
                                        @if ($assignment->valid_to === null)
                                            <x-action-form :action="route('club.fees.assignments.end', [$account, $assignment])" :confirm="__('club.fees.confirm.end_assignment')" confirm-icon="event_busy" confirm-tone="warning">
                                                <input type="hidden" name="valid_to" value="{{ $today->toDateString() }}">
                                                <x-icon-btn type="submit" icon="event_busy" tone="outline" size="xs" class="btn-warning" :label="__('club.fees.action.end_assignment')" />
                                            </x-action-form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="groups" :colspan="5" :title="__('club.fees.empty.assignments')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('club.fees.card.exemptions')" icon="money_off" :count="$exemptions->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.exemptions') }}</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($exemptions as $exemption)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $exemption->member?->fullName() }}</span>
                            <x-status-badge :tone="$exemption->kind === \App\Enums\Club\ClubFeeExemptionKind::Exemption ? 'warning' : 'info'" size="xs">{{ $exemption->kind->label() }}@if ($exemption->percent !== null) {{ $exemption->percent }} %@endif</x-status-badge>
                            <span class="tabular-nums">{{ $exemption->starts_on->format('d.m.Y') }} – {{ $exemption->ends_on?->format('d.m.Y') ?? __('club.label.open_end') }}</span>
                            <span class="text-xs text-muted">{{ $exemption->reason }}@if ($exemption->createdBy) · {{ $exemption->createdBy->name }}@endif</span>
                            @if ($canManage)
                                <span class="ml-auto flex gap-1">
                                    <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.exemptions.edit', [$account, $exemption])" :label="__('club.action.edit')" />
                                    <x-action-form :action="route('club.fees.exemptions.destroy', [$account, $exemption])" method="DELETE" :confirm="__('club.fees.confirm.delete_exemption')" confirm-icon="delete" confirm-tone="error">
                                        <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                    </x-action-form>
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.exemptions') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.fees.card.payer')" icon="account_balance_wallet">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('club.fees.field.customer') }}</dt>
                    <dd>@if ($account->customer)<a href="{{ route('customers.show', $account->customer) }}" class="link link-hover">{{ $account->customer->name }}</a>@if ($account->customer->number) <span class="text-xs text-muted">· {{ $account->customer->number }}</span>@endif @endif</dd>
                    <dt class="text-muted">{{ __('club.field.email') }}</dt><dd>{{ $account->email ?? '–' }}</dd>
                </dl>
                @if ($account->notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $account->notes }}</p>@endif
                <p class="mt-2 text-xs text-muted">{{ __('club.fees.hint.payer') }}</p>
            </x-card>

            {{-- Forderungen (MVP-850): offener Saldo und letzte Belege. --}}
            <x-card :title="__('club.fees.card.claims')" icon="receipt_long" :count="$claims->count()">
                <p class="text-2xl font-semibold tabular-nums {{ $openAmount->isPositive() ? 'text-warning' : '' }}">{{ $openAmount->format() }} <span class="text-sm font-normal text-muted">{{ __('club.fees.field.open_amount') }}</span></p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($claims as $claim)
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('club.fees.claims.show', $claim) }}" class="link link-hover font-medium">{{ $claim->number }}</a>
                            <span class="text-xs text-muted tabular-nums">{{ $claim->due_on->format('d.m.Y') }}</span>
                            <span class="ml-auto tabular-nums">{{ $claim->total->format() }}</span>
                            <x-status-badge :tone="$claim->status->tone()" size="xs">{{ $claim->status->label() }}</x-status-badge>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.claims') }}</li>
                    @endforelse
                </ul>
                <a href="{{ route('club.fees.claims.index', ['account' => $account->sqid, 'status' => 'all']) }}" class="link link-primary mt-2 inline-block text-sm">{{ __('club.fees.action.all_claims') }}</a>
            </x-card>

            {{-- Zahlungen und Guthaben (MVP-851). --}}
            <x-card :title="__('club.fees.card.payments')" icon="payments" :count="$payments->count()">
                @if ($credit->isPositive())
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-success">{{ __('club.fees.label.credit', ['amount' => $credit->format()]) }}</span>
                        @if ($canManage && $openAmount->isPositive())
                            <x-action-form :action="route('club.fees.payments.apply-credit', $account)">
                                <x-icon-btn type="submit" icon="sync_alt" tone="outline" size="xs" show-label>{{ __('club.fees.action.apply_credit') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                    </div>
                @endif
                <ul class="space-y-1 text-sm">
                    @forelse ($payments as $payment)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $payment->paid_on->format('d.m.Y') }}</span>
                            @if ($payment->claim)<a href="{{ route('club.fees.claims.show', $payment->claim) }}" class="link link-hover text-xs">{{ $payment->claim->number }}</a>@else<span class="text-xs text-muted">{{ __('club.fees.label.credit_entry') }}</span>@endif
                            <span class="text-xs text-muted">{{ $payment->method->label() }} · {{ $payment->source->label() }}</span>
                            <span class="ml-auto tabular-nums {{ $payment->amount->isNegative() ? 'text-error' : '' }}">{{ $payment->amount->format() }}</span>
                            @if ($canManage && $payment->amount->isPositive() && ! $payment->isChargeback())
                                <x-icon-btn icon="undo" tone="outline" size="xs" class="btn-warning" data-entry-modal-trigger :href="route('club.fees.payments.chargeback.edit', [$account, $payment])" :label="__('club.fees.action.chargeback')" />
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.payments') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('club.fees.card.collection')" icon="account_balance">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('club.fees.field.mandate') }}</dt><dd>@if ($mandate){{ $mandate->reference }} <x-status-badge :tone="$mandate->isUsable() ? 'success' : 'warning'" size="xs">{{ $mandate->status->label() }}</x-status-badge>@else<span class="text-muted">{{ __('club.fees.label.no_mandate') }}</span>@endif</dd>
                    <dt class="text-muted">{{ __('club.fees.field.portal_user') }}</dt><dd>{{ $portalUser?->name ?? '–' }}</dd>
                </dl>
                <p class="mt-2 text-xs text-muted">{{ __('club.fees.hint.collection_account') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

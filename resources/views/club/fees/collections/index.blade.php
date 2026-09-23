{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- SEPA-Einzug für Beitragsforderungen (MVP-851): Vorschlag, Sammellauf über die Finanz-Zahlungsläufe, Export nur mit Finanzpaket. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.collections'))
@section('nav-title', __('club.fees.title.collections'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.fees.subtitle.collections')">
            <x-slot:actions>
                <x-icon-btn icon="receipt_long" tone="outline" size="sm" :href="route('club.fees.claims.index')" show-label>{{ __('club.fees.title.claims') }}</x-icon-btn>
                <x-icon-btn icon="account_balance" tone="ghost" size="sm" :href="route('finance.mandates.index')" show-label>{{ __('club.fees.action.manage_mandates') }}</x-icon-btn>
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
    @unless ($formatsAvailable)
        <div class="alert alert-info text-sm" role="status"><x-icon name="info" /><span>{{ __('club.fees.hint.formats_missing') }}</span></div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.fees.card.collection_proposals')" icon="account_balance" :count="$proposals->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.collection') }}</p>
                @if ($canManage && $bankAccounts->isNotEmpty() && $proposals->contains(fn($row) => $row['blocked'] === null))
                    <form method="POST" action="{{ route('club.fees.collections.store') }}" class="space-y-2">
                        @csrf
                        <x-table :bare="true" size="sm">
                            <x-slot:head>
                                <tr>
                                    <th></th>
                                    <th>{{ __('club.fees.field.number') }}</th>
                                    <th>{{ __('club.fees.field.account') }}</th>
                                    <th>{{ __('club.fees.field.due_on') }}</th>
                                    <th>{{ __('club.fees.field.mandate') }}</th>
                                    <th class="text-right">{{ __('club.fees.field.open_amount') }}</th>
                                </tr>
                            </x-slot:head>
                            @foreach ($proposals as $row)
                                <tr class="{{ $row['blocked'] ? 'opacity-60' : '' }}">
                                    <td>@if ($row['blocked'] === null)<input type="checkbox" class="checkbox checkbox-sm" name="claim_ids[]" value="{{ $row['claim']->sqid }}" checked aria-label="{{ $row['claim']->number }}">@endif</td>
                                    <td class="font-medium"><a href="{{ route('club.fees.claims.show', $row['claim']) }}" class="link link-hover">{{ $row['claim']->number }}</a></td>
                                    <td class="text-sm">{{ $row['account']->name }}</td>
                                    <td class="whitespace-nowrap text-sm tabular-nums">{{ $row['claim']->due_on->format('d.m.Y') }}</td>
                                    <td class="text-xs">{{ $row['mandate']?->reference ?? '–' }} @if ($row['blocked'])<x-status-badge tone="warning" size="xs" :label="$row['blocked']" />@endif</td>
                                    <td class="text-right tabular-nums">{{ $row['amount']->format() }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-select-field name="bank_account_id" :label="__('club.fees.field.bank_account')" required>
                                @foreach ($bankAccounts as $bankAccount)
                                    <option value="{{ $bankAccount->sqid }}">{{ $bankAccount->label }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field name="execution_date" type="date" :label="__('club.fees.field.execution_date')" :value="old('execution_date')" :hint="__('club.fees.hint.execution_date')" />
                            <x-icon-btn type="submit" icon="playlist_add_check" tone="primary" size="sm" show-label>{{ __('club.fees.action.create_collection') }}</x-icon-btn>
                        </div>
                    </form>
                @elseif ($proposals->isEmpty())
                    <p class="text-sm text-muted">{{ __('club.fees.empty.collection') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($proposals as $row)
                            <li class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ $row['claim']->number }}</span><span>{{ $row['account']->name }}</span><span class="tabular-nums">{{ $row['amount']->format() }}</span>@if ($row['blocked'])<x-status-badge tone="warning" size="xs" :label="$row['blocked']" />@endif</li>
                        @endforeach
                    </ul>
                    @if ($bankAccounts->isEmpty())<p class="mt-2 text-xs text-muted">{{ __('club.fees.hint.no_bank_account') }}</p>@endif
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.fees.card.collection_runs')" icon="playlist_add_check" :count="$runs->count()">
                <ul class="space-y-2 text-sm">
                    @forelse ($runs as $run)
                        <li class="rounded-box border border-base-300 px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('finance.payment-runs.show', $run) }}" class="link link-hover font-medium">{{ $run->label ?? '#' . $run->id }}</a>
                                <x-status-badge :tone="$run->isExported() ? 'success' : ($run->isReleased() ? 'info' : ($run->status === \App\Enums\Finance\PaymentRunStatus::Cancelled ? 'warning' : 'neutral'))" size="xs">{{ $run->status->label() }}</x-status-badge>
                                <span class="ml-auto tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $run->total, 2, withThousandsSeparator: true) }}</span>
                            </div>
                            <p class="text-xs text-muted">{{ __('club.fees.field.execution_date') }}: {{ $run->execution_date?->format('d.m.Y') }}</p>
                            @if ($canManage)
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($run->isDraft() || $run->isReleased())
                                        <x-action-form :action="route('club.fees.collections.cancel', $run)" :confirm="__('club.fees.confirm.cancel_collection')" confirm-icon="close" confirm-tone="warning">
                                            <x-icon-btn type="submit" icon="close" tone="outline" size="xs" class="btn-warning" show-label>{{ __('club.fees.action.cancel_collection') }}</x-icon-btn>
                                        </x-action-form>
                                    @endif
                                    @if ($run->isExported())
                                        <x-action-form :action="route('club.fees.collections.settle', $run)" :confirm="__('club.fees.confirm.settle_collection')" confirm-icon="check_circle" confirm-tone="primary">
                                            <input type="hidden" name="paid_on" value="{{ $today->toDateString() }}">
                                            <x-icon-btn type="submit" icon="check_circle" tone="primary" size="xs" show-label>{{ __('club.fees.action.settle_collection') }}</x-icon-btn>
                                        </x-action-form>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.collection_runs') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.fees.hint.collection_runs') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

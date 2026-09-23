{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fees.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meine Beiträge (MVP-851): Beitragsmitteilungen, Fälligkeiten, Zahlungen und offene Beträge für die zahlungspflichtige Person. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.my'))
@section('nav-title', __('club.fees.title.my'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.fees.subtitle.my')">
            <x-slot:actions>
                @if ($hasSubjects)
                    <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.my.index')" show-label>{{ __('club.my.action.back') }}</x-icon-btn>
                @endif
                <x-help-button topic="club.my" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @foreach ($accounts as $row)
        <x-card :title="$row['account']->name" icon="account_balance_wallet">
            <p class="text-2xl font-semibold tabular-nums {{ $row['open']->isPositive() ? 'text-warning' : '' }}">{{ $row['open']->format() }} <span class="text-sm font-normal text-muted">{{ __('club.fees.field.open_amount') }}</span></p>
            @if ($row['credit']->isPositive())<p class="text-sm text-success">{{ __('club.fees.label.credit', ['amount' => $row['credit']->format()]) }}</p>@endif
            <x-table :bare="true" size="sm" class="mt-2">
                <x-slot:head>
                    <tr>
                        <th>{{ __('club.fees.field.number') }}</th>
                        <th>{{ __('club.field.period') }}</th>
                        <th>{{ __('club.fees.field.due_on') }}</th>
                        <th>{{ __('club.field.status') }}</th>
                        <th class="text-right">{{ __('club.fees.field.amount') }}</th>
                        <th class="text-right">{{ __('club.fees.field.open_amount') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($row['claims'] as $claim)
                    <tr>
                        <td class="font-medium">{{ $claim->number }}</td>
                        <td class="whitespace-nowrap text-sm tabular-nums">{{ $claim->period_start->format('d.m.Y') }} – {{ $claim->period_end->format('d.m.Y') }}</td>
                        <td class="whitespace-nowrap text-sm tabular-nums {{ $claim->isOverdue($today) ? 'text-error font-medium' : '' }}">{{ $claim->due_on->format('d.m.Y') }}</td>
                        <td><x-status-badge :tone="$claim->status->tone()" size="sm">{{ $claim->status->label() }}</x-status-badge></td>
                        <td class="text-right tabular-nums">{{ $claim->total->format() }}</td>
                        <td class="text-right tabular-nums">{{ $claim->openAmount()->format() }}</td>
                        <td class="text-right"><x-icon-btn icon="picture_as_pdf" tone="ghost" size="xs" :href="route('club.my.fees.pdf', $claim)" :label="__('club.fees.action.pdf')" /></td>
                    </tr>
                @empty
                    <x-table.empty icon="receipt_long" :colspan="7" :title="__('club.fees.empty.claims')" compact />
                @endforelse
            </x-table>
            @if ($row['payments']->isNotEmpty())
                <p class="mt-3 text-xs font-medium text-muted">{{ __('club.fees.card.payments') }}</p>
                <ul class="space-y-0.5 text-sm">
                    @foreach ($row['payments'] as $payment)
                        <li class="flex flex-wrap items-center gap-2"><span class="tabular-nums">{{ $payment->paid_on->format('d.m.Y') }}</span><span>{{ $payment->method->label() }}</span>@if ($payment->claim)<span class="text-xs text-muted">{{ $payment->claim->number }}</span>@endif<span class="ml-auto tabular-nums {{ $payment->amount->isNegative() ? 'text-error' : '' }}">{{ $payment->amount->format() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endforeach
    @if ($accounts->isEmpty())
        <x-empty-state framed icon="account_balance_wallet" :title="__('club.fees.empty.my')" />
    @endif
    <p class="text-xs text-muted">{{ __('club.fees.hint.my') }}</p>
</x-page-shell>
@endsection

{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : billing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rechnungen und Zahlungen je LV, Abschlag aus dem Leistungsstand (MVP-932). Erwartet: $bill, $proposal, $history, $canInvoice --}}
@extends('layouts.app')

@section('title', __('gaeb.billing.title') . ' — ' . $bill->name)
@section('nav-title', __('gaeb.billing.title'))

@php
    $money = static fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
    $cur = $proposal['currency'];
@endphp

@section('content')
<x-index-page :subtitle="$bill->name">

    @include('bill-of-quantities._tabs')

    <x-card :title="__('gaeb.billing.proposal')">
        <div class="flex flex-wrap items-end gap-6">
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.billing.executed', ['percent' => (int) round($proposal['progress'] * 100)]) }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ $money($proposal['executed']) }} {{ $cur }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.billing.previous') }}</div>
                <div class="text-lg font-semibold tabular-nums">− {{ $money($proposal['previous']) }} {{ $cur }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.billing.amount') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ $money($proposal['amount']) }} {{ $cur }}</div>
            </div>
            @if ($canInvoice && $proposal['amount'] > 0.0)
                <form method="POST" action="{{ route('bill-of-quantities.billing.store', $bill) }}" class="flex items-end gap-2">
                    @csrf
                    <x-input-field name="amount" type="number" step="0.01" min="0.01" :max="$proposal['amount']" :label="__('gaeb.billing.net')" :value="old('amount', \CommonToolkit\Helper\Data\NumberHelper::toUSFormat($proposal['amount'], 2))" required />
                    <x-button type="submit" size="sm">{{ __('gaeb.billing.create') }}</x-button>
                </form>
            @endif
        </div>
        <p class="mt-2 text-xs opacity-70">{{ __('gaeb.billing.hint') }}</p>
    </x-card>

    <x-card padding="p-0" class="mt-4" :title="__('gaeb.billing.invoices')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('gaeb.billing.field.number') }}</th>
                    <th>{{ __('gaeb.billing.field.type') }}</th>
                    <th>{{ __('gaeb.billing.field.issued_on') }}</th>
                    <th>{{ __('gaeb.billing.field.status') }}</th>
                    <th class="text-right">{{ __('gaeb.billing.field.net') }}</th>
                    <th class="text-right">{{ __('gaeb.billing.field.gross') }}</th>
                    <th class="text-right">{{ __('gaeb.billing.field.paid') }}</th>
                    <th class="text-right">{{ __('gaeb.billing.field.open') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($history['rows'] as $row)
                <tr>
                    <td><a class="link" href="{{ route('invoices.show', $row['invoice']) }}">{{ $row['invoice']->number }}</a></td>
                    <td>{{ $row['invoice']->documentLabel() }}</td>
                    <td>{{ $row['invoice']->issued_on?->fdate() ?? '—' }}</td>
                    <td><span class="wd-badge badge-ghost">{{ $row['invoice']->status->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $money($row['invoice']->subtotal?->toFloat() ?? 0.0) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['invoice']->total?->toFloat() ?? 0.0) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['paid']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['open']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="receipt_long" :colspan="8" :title="__('gaeb.billing.empty')" compact />
            @endforelse
            @if ($history['rows'] !== [])
                <tr class="font-semibold">
                    <td colspan="4">{{ __('gaeb.billing.total') }}</td>
                    <td class="text-right tabular-nums">{{ $money($history['totals']['net']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($history['totals']['gross']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($history['totals']['paid']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($history['totals']['open']) }}</td>
                </tr>
            @endif
        </x-table>
    </x-card>

    <x-card padding="p-0" class="mt-4" :title="__('gaeb.billing.payments')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('gaeb.billing.field.date') }}</th>
                    <th>{{ __('gaeb.billing.field.number') }}</th>
                    <th>{{ __('gaeb.billing.field.source') }}</th>
                    <th class="text-right">{{ __('gaeb.billing.field.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($history['payments'] as $payment)
                <tr>
                    <td>{{ $payment['date']?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $payment['invoice']->number }}</td>
                    <td>{{ $payment['source'] }}</td>
                    <td class="text-right tabular-nums">{{ $money($payment['amount']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="payments" :colspan="4" :title="__('gaeb.billing.no_payments')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection

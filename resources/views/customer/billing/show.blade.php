{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('customer.layout')

@php
    $money = fn ($v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
    $hours = fn (int $m): string => \App\Support\Formats::duration($m);
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div>
            <a href="{{ route('customer.billing.index') }}" class="text-sm link link-hover">&larr; {{ __('customer-billing.portal_title') }}</a>
            <h1 class="text-2xl font-semibold">
                {{ $statement->periodLabel() }}
                @unless ($locked)
                    <span class="badge badge-ghost align-middle">{{ __('customer-billing.provisional') }}</span>
                @endunless
            </h1>
        </div>
        <a href="{{ $pdfUrl }}" class="btn btn-sm btn-primary">{{ __('customer-billing.download_pdf') }}</a>
    </div>

    {{-- Abrechnungsblock (Excel-Analogie Gesamt/Abgerechnet/Vormonat/Offen) --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
        <div class="rounded-box border border-base-300 p-3">
            <div class="text-xs text-base-content/60">{{ __('customer-billing.gross_value') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->gross_value) }}</div>
            <div class="text-xs text-base-content/60 tabular-nums">{{ $hours($statement->total_minutes) }}</div>
        </div>
        <div class="rounded-box border border-base-300 p-3">
            <div class="text-xs text-base-content/60">{{ __('customer-billing.payments_total') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->payments_total) }}</div>
        </div>
        <div class="rounded-box border border-base-300 p-3">
            <div class="text-xs text-base-content/60">{{ __('customer-billing.carry_in') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->carry_in) }}</div>
        </div>
        <div class="rounded-box border border-primary/40 bg-primary/5 p-3">
            <div class="text-xs text-base-content/60">{{ __('customer-billing.balance') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->balance) }}</div>
        </div>
    </div>

    <h2 class="text-lg font-medium mb-2">{{ __('customer-billing.attendance') }}</h2>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('customer-billing.weekday') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.date') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.reason') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.start') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.end') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.duration') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.amount') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['weekday'] }}</td>
                <td class="whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($row['date'])->fdate() }}</td>
                <td>{{ $row['category'] }}</td>
                <td class="tabular-nums">{{ $row['start'] }}</td>
                <td class="tabular-nums">{{ $row['end'] }}</td>
                <td class="text-right tabular-nums">{{ $hours((int) $row['minutes']) }}</td>
                <td class="text-right tabular-nums">{{ $money($row['amount']) }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="7" :title="__('customer-billing.no_entries')" />
        @endforelse
    </x-table>

    @if ($payments !== [])
        <h2 class="text-lg font-medium mt-6 mb-2">{{ __('customer-billing.recent_payments') }}</h2>
        <x-table>
            @foreach ($payments as $payment)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($payment['paid_on'])->fdate() }}</td>
                    <td class="text-right tabular-nums">{{ $money($payment['amount']) }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif
@endsection

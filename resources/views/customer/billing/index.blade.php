{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('customer.layout')

@php
    $money = fn ($v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
    $hours = fn (int $m) => sprintf('%d:%02d', intdiv($m, 60), $m % 60);
@endphp

@section('content')
    <h1 class="text-2xl font-semibold mb-1">{{ __('customer-billing.portal_title') }}</h1>
    <p class="text-sm text-base-content/60 mb-4">{{ __('customer-billing.portal_intro') }}</p>

    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('customer-billing.month') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.hours') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.gross_value') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.payments_total') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.carry_in') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.balance') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($statements as $statement)
            <tr>
                <td class="whitespace-nowrap">
                    <a href="{{ route('customer.billing.show', ['year' => $statement->year, 'month' => $statement->month]) }}" class="link link-hover">
                        {{ $statement->periodLabel() }}
                    </a>
                </td>
                <td class="text-right tabular-nums">{{ $hours($statement->total_minutes) }}</td>
                <td class="text-right tabular-nums">{{ $money($statement->gross_value) }}</td>
                <td class="text-right tabular-nums">{{ $money($statement->payments_total) }}</td>
                <td class="text-right tabular-nums">{{ $money($statement->carry_in) }}</td>
                <td class="text-right tabular-nums font-medium">{{ $money($statement->balance) }}</td>
                <td>
                    @unless ($statement->locked)
                        <span class="badge badge-ghost badge-sm">{{ __('customer-billing.provisional') }}</span>
                    @endunless
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" :title="__('customer-billing.no_statements')" />
        @endforelse
    </x-table>
@endsection

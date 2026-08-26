{{--
  Created on   : Sat Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : statement.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Monatsdetail des Kundenkontos für die Verwaltung (Feature 098): dieselben
     Zeilen wie Portal und PDF, damit die Bewertung je Eintrag (Satz, Anfahrt)
     prüfbar ist. Datenquelle bleibt CustomerAccountStatementService::monthData. --}}

@extends('layouts.app')

@section('title', $customer->name . ' — ' . $statement->periodLabel())
@section('nav-title', __('customer-billing.statement_detail'))

@php
    $money = fn ($v) => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
    $hours = fn (int $m): string => \App\Support\Formats::duration($m);
    $travelMinutes = collect($rows)->sum(fn (array $row): int => (int) ($row['travel_minutes'] ?? 0));
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $customer->name }} — {{ $statement->periodLabel() }}</x-slot:title>
            <x-slot:subtitle>{{ $locked ? __('customer-billing.locked_snapshot_hint') : __('customer-billing.provisional') }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('customers.show', $customer) . '#customer-billing'"
                            show-label>{{ __('customer-billing.back_to_customer') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs text-muted">{{ __('customer-billing.gross_value') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->gross_value) }}</div>
            <div class="text-xs text-muted tabular-nums">
                {{ $hours((int) $statement->total_minutes) }}
                @if ($travelMinutes > 0)
                    + {{ $hours($travelMinutes) }} {{ __('customer-billing.travel') }}
                @endif
            </div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs text-muted">{{ __('customer-billing.payments_total') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->payments_total) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs text-muted">{{ __('customer-billing.carry_in') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->carry_in) }}</div>
        </div>
        <div class="rounded-box border border-primary/40 bg-primary/5 p-3">
            <div class="text-xs text-muted">{{ __('customer-billing.balance') }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ $money($statement->balance) }}</div>
        </div>
    </div>

    <x-card :title="__('customer-billing.attendance')" padding="p-0">
        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('customer-billing.weekday') }}</x-table.th>
                    <x-table.th>{{ __('customer-billing.date') }}</x-table.th>
                    <x-table.th>{{ __('customer-billing.reason') }}</x-table.th>
                    <x-table.th>{{ __('customer-billing.start') }}</x-table.th>
                    <x-table.th>{{ __('customer-billing.end') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('customer-billing.duration') }}</x-table.th>
                    @if ($travelMinutes > 0)
                        <x-table.th class="text-right">{{ __('customer-billing.travel') }}</x-table.th>
                    @endif
                    <x-table.th class="text-right">{{ __('customer-billing.hourly_rate') }}</x-table.th>
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
                    @if ($travelMinutes > 0)
                        <td class="text-right tabular-nums">{{ (int) ($row['travel_minutes'] ?? 0) > 0 ? $hours((int) $row['travel_minutes']) : '—' }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $money($row['hourly_rate']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['amount']) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="$travelMinutes > 0 ? 9 : 8" :title="__('customer-billing.no_entries')" />
            @endforelse
        </x-table>
    </x-card>

    @if ($byCategory !== [])
        <x-card :title="__('customer-billing.by_category')" padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('customer-billing.activity_category') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('customer-billing.duration') }}</x-table.th>
                        @if ($travelMinutes > 0)
                            <x-table.th class="text-right">{{ __('customer-billing.travel') }}</x-table.th>
                        @endif
                        <x-table.th class="text-right">{{ __('customer-billing.amount') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($byCategory as $group)
                    <tr>
                        <td>{{ $group['label'] }}</td>
                        <td class="text-right tabular-nums">{{ $hours((int) $group['minutes']) }}</td>
                        @if ($travelMinutes > 0)
                            <td class="text-right tabular-nums">{{ (int) ($group['travel_minutes'] ?? 0) > 0 ? $hours((int) $group['travel_minutes']) : '—' }}</td>
                        @endif
                        <td class="text-right tabular-nums">{{ $money($group['amount']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if ($payments !== [])
        <x-card :title="__('customer-billing.recent_payments')" padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('customer-billing.paid_on') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('customer-billing.amount') }}</x-table.th>
                        <x-table.th>{{ __('customer-billing.source') }}</x-table.th>
                        <x-table.th>{{ __('customer-billing.note') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($payment['paid_on'])->fdate() }}</td>
                        <td class="text-right tabular-nums">{{ $money($payment['amount']) }}</td>
                        <td>{{ \App\Enums\Billing\AccountPaymentSource::from($payment['source'])->label() }}</td>
                        <td class="text-base-content/70">{{ $payment['note'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

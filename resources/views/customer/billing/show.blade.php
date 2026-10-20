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
    // Aus den Zeilen, nicht aus der Statement-Spalte: gesperrte Monate zeigen
    // ihren eingefrorenen Snapshot (Altmonate ohne Anfahrt bleiben unverändert).
    $travelMinutes = collect($rows)->sum(fn (array $row): int => (int) ($row['travel_minutes'] ?? 0));
    // Anfahrt gesondert nur, wenn die Vereinbarung es ausweist (MVP-1235); sonst steckt sie je Zeile in der Dauer.
    $itemizeTravel = $travelMinutes > 0 && (bool) ($agreement->is_travel_itemized ?? true);
    $rowMinutes = fn (array $row): int => (int) $row['minutes'] + ($itemizeTravel ? 0 : (int) ($row['travel_minutes'] ?? 0));
    $totalMinutes = (int) $statement->total_minutes + ($itemizeTravel ? 0 : $travelMinutes);
@endphp

@section('content')
    <x-page-toolbar :title="$statement->periodLabel()" :back="route('customer.billing.index')" :back-label="__('customer-billing.portal_title')" class="mb-4">
        @unless ($locked)
            <x-slot:badges>
                <x-status-badge tone="warning" size="sm" outline>{{ __('customer-billing.provisional') }}</x-status-badge>
            </x-slot:badges>
        @endunless
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" tone="primary" :href="$pdfUrl" show-label>{{ __('customer-billing.download_pdf') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Abrechnungsblock (Excel-Analogie Gesamt/Abgerechnet/Vormonat/Offen) --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
        <x-kpi-tile :label="__('customer-billing.gross_value')" :value="$money($statement->gross_value)" format="text"
                    :hint="$hours($totalMinutes) . ($itemizeTravel ? ' + ' . $hours($travelMinutes) . ' ' . __('customer-billing.travel') : '')" />
        <x-kpi-tile :label="__('customer-billing.payments_total')" :value="$money($statement->payments_total)" format="text" tone="success" />
        <x-kpi-tile :label="__('customer-billing.carry_in')" :value="$money($statement->carry_in)" format="text" />
        <x-kpi-tile :label="__('customer-billing.balance')" :value="$money($statement->balance)" format="text" tone="primary" />
    </div>

    <x-card :title="__('customer-billing.attendance')" icon="schedule" padding="p-0">
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('customer-billing.weekday') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.date') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.reason') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.start') }}</x-table.th>
                <x-table.th>{{ __('customer-billing.end') }}</x-table.th>
                <x-table.th class="text-right">{{ __('customer-billing.duration') }}</x-table.th>
                @if ($itemizeTravel)
                    <x-table.th class="text-right">{{ __('customer-billing.travel') }}</x-table.th>
                @endif
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
                <td class="text-right tabular-nums">{{ $hours($rowMinutes($row)) }}</td>
                @if ($itemizeTravel)
                    <td class="text-right tabular-nums">{{ (int) ($row['travel_minutes'] ?? 0) > 0 ? $hours((int) $row['travel_minutes']) : '—' }}</td>
                @endif
                <td class="text-right tabular-nums">{{ $money($row['amount']) }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="$itemizeTravel ? 8 : 7" icon="schedule" :title="__('customer-billing.no_entries')" compact />
        @endforelse
    </x-table>
    </x-card>

    @if ($payments !== [])
        <x-card :title="__('customer-billing.recent_payments')" icon="payments" padding="p-0" class="mt-6">
        <x-table bare>
            @foreach ($payments as $payment)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($payment['paid_on'])->fdate() }}</td>
                    <td class="text-right tabular-nums">{{ $money($payment['amount']) }}</td>
                </tr>
            @endforeach
        </x-table>
        </x-card>
    @endif
@endsection

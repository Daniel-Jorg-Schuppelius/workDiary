{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    @php
        // Jahr als Tabs, Status und Monat als Filter (MVP-1243) — Ziel der Drilldowns aus der Auswertung.
        $yearTabs = collect($years)->map(fn (int $y): array => [
            'label' => (string) $y,
            'href' => route('customer.invoices.index', array_filter(['year' => $y, 'status' => $status])),
            'active' => $y === $year,
        ])->all();
    @endphp
    <x-page-toolbar :title="__('Rechnungen')" :subtitle="__('customer_portal.nav.invoices')" class="mb-4" />
    <div class="space-y-4">
    <x-tab-nav :items="$yearTabs" class="w-fit max-w-full flex-nowrap overflow-x-auto" aria-label="{{ __('customer_portal.time.years_nav') }}" />
    <x-filter-bar :action="route('customer.invoices.index')" method="GET" :reset="route('customer.invoices.index', ['year' => $year])">
        <input type="hidden" name="year" value="{{ $year }}">
        <x-filter-field :label="__('customer_portal.invoices_list.filter_status')" for="portal-invoice-status">
            <select id="portal-invoice-status" name="status" class="select select-sm shrink-0">
                <option value="">{{ __('customer_portal.invoices_list.status_all') }}</option>
                <option value="open" @selected($status === 'open')>{{ __('customer_portal.invoices_list.status_open') }}</option>
                <option value="paid" @selected($status === 'paid')>{{ __('customer_portal.invoices_list.status_paid') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('customer_portal.invoices_list.filter_month')" for="portal-invoice-month">
            <select id="portal-invoice-month" name="month" class="select select-sm shrink-0">
                <option value="">{{ __('customer_portal.invoices_list.month_all') }}</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\CarbonImmutable::create($year, $m, 1)->isoFormat('MMMM') }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>
    <x-card>
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Datum') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th class="text-right">{{ __('Betrag') }}</x-table.th>
                <x-table.th class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($invoices as $row)
            <tr>
                <td>{{ $row['number'] }}</td>
                <td class="whitespace-nowrap">{{ optional($row['date'])->fdate() }}</td>
                <td><x-status-badge size="sm" :tone="$row['open'] ? 'warning' : 'success'" outline>{{ $row['statusLabel'] }}</x-status-badge></td>
                <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['total'], 2, withThousandsSeparator: true) }} {{ $row['currency'] }}</td>
                <td class="text-right whitespace-nowrap">
                    <x-icon-btn icon="picture_as_pdf" :href="$row['pdf']"
                                :label="__('customer_portal.invoices.pdf_label', ['number' => $row['number']])" />
                    @if ($row['invoice'] !== null && $payLinks->has($row['invoice']->id))
                        <x-button :href="$payLinks->get($row['invoice']->id)">{{ __('payments.portal.pay') }}</x-button>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" icon="receipt_long" :title="$status !== null || $month !== null ? __('customer_portal.invoices_list.empty_filtered') : __('Keine Rechnungen vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>
    </div>
    <x-pagination :paginator="$invoices" standing />
@endsection

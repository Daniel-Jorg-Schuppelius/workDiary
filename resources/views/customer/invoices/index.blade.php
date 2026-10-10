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
    <h1 class="text-2xl font-semibold mb-4">{{ __('Rechnungen') }}</h1>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Datum') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th class="text-right">{{ __('Betrag') }}</x-table.th>
                <x-table.th class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }}</td>
                <td class="whitespace-nowrap">{{ optional($invoice->issued_on)->fdate() }}</td>
                <td>{{ $invoice->status->label() }}</td>
                <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->total?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
                <td class="text-right whitespace-nowrap">
                    @unless ($externallyLed->contains($invoice->id))
                        <x-icon-btn icon="picture_as_pdf" :href="route('customer.invoices.pdf', $invoice)"
                                    :label="__('customer_portal.invoices.pdf_label', ['number' => $invoice->number])" />
                    @endunless
                    @if ($payLinks->has($invoice->id))
                        <x-button :href="$payLinks->get($invoice->id)">{{ __('payments.portal.pay') }}</x-button>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" :title="__('Keine Rechnungen vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$invoices" standing />
@endsection

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
            </tr>
        </x-slot:head>
        @forelse ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }}</td>
                <td class="whitespace-nowrap">{{ optional($invoice->issued_on)->fdate() }}</td>
                <td>{{ $invoice->status }}</td>
                <td class="text-right">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency->value }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Keine Rechnungen vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$invoices" />
@endsection

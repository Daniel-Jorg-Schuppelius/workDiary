@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Rechnungen') }}</h1>
    @if ($invoices->isEmpty())
        <div class="bg-base-100 border border-base-300 rounded p-6 text-center text-base-content/60">
            {{ __('Keine Rechnungen vorhanden.') }}
        </div>
    @else
        <table class="w-full bg-base-100 border border-base-300 rounded text-sm">
            <thead>
                <tr class="border-b border-base-300">
                    <th class="text-left p-2">{{ __('Nummer') }}</th>
                    <th class="text-left p-2">{{ __('Datum') }}</th>
                    <th class="text-left p-2">{{ __('Status') }}</th>
                    <th class="text-right p-2">{{ __('Betrag') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr class="border-b border-base-200">
                        <td class="p-2">{{ $invoice->number }}</td>
                        <td class="p-2 whitespace-nowrap">{{ optional($invoice->issued_on)->format('d.m.Y') }}</td>
                        <td class="p-2">{{ $invoice->status }}</td>
                        <td class="p-2 text-right">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
@endsection

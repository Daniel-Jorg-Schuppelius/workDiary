@extends('layouts.app')

@section('title', __('Rechnungen'))
@section('nav-title', __('Rechnungen'))

@section('content')
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">{{ __('Rechnungen') }}</h1>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="add"/> {{ __('Neue Rechnung') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="overflow-x-auto bg-base-100 rounded-box shadow">
        <table class="table table-zebra table-sm">
            <thead>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Summe') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="link">{{ $invoice->number }}</a></td>
                        <td>{{ $invoice->customer->name ?? '-' }}</td>
                        <td>{{ optional($invoice->issued_on)->format('d.m.Y') ?? '-' }}</td>
                        <td><span class="badge badge-outline">{{ __($invoice->status) }}</span></td>
                        <td class="text-right">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-xs">{{ __('Anzeigen') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center opacity-60">{{ __('Keine Rechnungen vorhanden.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $invoices->links() }}
</div>
@endsection

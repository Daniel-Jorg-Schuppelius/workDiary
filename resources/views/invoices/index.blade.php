@extends('layouts.app')

@section('title', __('Rechnungen'))
@section('nav-title', __('Rechnungen'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <a href="{{ route('invoices.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    <x-icon name="add"/> {{ __('Neue Rechnung') }}
                </a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <div class="overflow-x-auto">
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
                    <tr><td colspan="6" class="p-0"><x-empty-state :compact="true" :title="__('Keine Rechnungen vorhanden')" /></td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-card>

    {{ $invoices->links() }}
</x-page-shell>
@endsection

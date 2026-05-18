@extends('layouts.app')

@section('title', __('Rechnung :nr', ['nr' => $invoice->number]))
@section('nav-title', $invoice->number)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-2">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Rechnung') }} {{ $invoice->number }}</h1>
            <div class="text-sm opacity-70">
                {{ $invoice->customer->name }} ·
                <span class="badge badge-outline">{{ __($invoice->status) }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-sm">{{ __('PDF') }}</a>
            @can('issue', $invoice)
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf
                    <button class="btn btn-sm btn-primary">{{ __('Stellen') }}</button>
                </form>
            @endcan
            @can('pay', $invoice)
                <form method="POST" action="{{ route('invoices.pay', $invoice) }}">@csrf
                    <button class="btn btn-sm btn-success">{{ __('Bezahlt markieren') }}</button>
                </form>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Wirklich löschen?') }}"
                      data-confirm-icon="delete"
                      data-confirm-tone="error"
                      data-confirm-label="{{ __('Löschen') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-error btn-outline">{{ __('Löschen') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <x-table table-sort="client">
        <x-slot:head>
            <tr>
                <th>#</th>
                <x-table.th sort>{{ __('Beschreibung') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Einzelpreis') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
            </tr>
        </x-slot:head>
        <x-slot:foot>
            <tr><td colspan="4" class="text-right">{{ __('Zwischensumme') }}</td><td class="text-right">{{ number_format((float) $invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
            <tr><td colspan="4" class="text-right">{{ __('USt.') }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%</td><td class="text-right">{{ number_format((float) $invoice->tax_amount, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
            <tr><td colspan="4" class="text-right font-bold">{{ __('Gesamt') }}</td><td class="text-right font-bold">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        </x-slot:foot>
        @forelse ($invoice->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->quantity }}">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->unit_price }}">{{ number_format((float) $item->unit_price, 2, ',', '.') }} {{ $invoice->currency }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->amount }}">{{ number_format((float) $item->amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="5" :title="__('Keine Positionen.')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection

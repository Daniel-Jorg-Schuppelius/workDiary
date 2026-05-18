@extends('layouts.app')

@section('title', __('Rechnung :nr', ['nr' => $invoice->number]))
@section('nav-title', $invoice->number)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-page-toolbar :title="__('Rechnung') . ' ' . $invoice->number" :badge="__($invoice->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">{{ $invoice->customer->name }}</div>
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('invoices.pdf', $invoice)" show-label>{{ __('PDF') }}</x-icon-btn>
            @can('issue', $invoice)
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="inline">@csrf
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Stellen') }}</x-icon-btn>
                </form>
            @endcan
            @can('pay', $invoice)
                <form method="POST" action="{{ route('invoices.pay', $invoice) }}" class="inline">@csrf
                    <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Bezahlt markieren') }}</x-icon-btn>
                </form>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Wirklich löschen?') }}"
                      data-confirm-icon="delete"
                      data-confirm-tone="error"
                      data-confirm-label="{{ __('Löschen') }}">
                    @csrf @method('DELETE')
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

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

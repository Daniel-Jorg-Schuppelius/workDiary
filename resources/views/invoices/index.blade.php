@extends('layouts.app')

@section('title', __('Rechnungen'))
@section('nav-title', __('Rechnungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Rechnungen erstellen, versenden und nachverfolgen.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('invoices.create')"
                    show-label>{{ __('Neue Rechnung') }}</x-icon-btn>
    </x-slot:actions>

    @include('billing._tabs')

    <x-filter-bar :action="route('invoices.index')" :reset="route('invoices.index')">
        <select name="customer" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Kunde') }}">
            <option value="">{{ __('Alle Kunden') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((int) ($filters['customer'] ?? 0) === (int) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="status" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table table-sort="server"
                 :route="route('invoices.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="[]"
                 bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="number">{{ __('Nummer') }}</x-table.th>
                    <th>{{ __('Kunde') }}</th>
                    <x-table.th sort="issued_on" default>{{ __('Datum') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="total" align="right">{{ __('Summe') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('invoices.show', $invoice) }}" class="link">{{ $invoice->number }}</a></td>
                    <td>{{ $invoice->customer->name ?? '-' }}</td>
                    <td>{{ optional($invoice->issued_on)->format('d.m.Y') ?? '-' }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$invoice->status}") }}</x-status-badge></td>
                    <td class="text-right">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td>
                    <td>
                        <x-icon-btn icon="visibility"
                                    :href="route('invoices.show', $invoice)"
                                    :label="__('Anzeigen')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="6" :title="__('Keine Rechnungen vorhanden')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$invoices" />
</x-index-page>
@endsection

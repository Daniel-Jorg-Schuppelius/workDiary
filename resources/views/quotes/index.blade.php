@extends('layouts.app')

@section('title', __('Angebote'))
@section('nav-title', __('Angebote'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Angebote erstellen, versionieren, versenden und in Rechnungen überführen.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('quotes.create')"
                    show-label>{{ __('Neues Angebot') }}</x-icon-btn>
    </x-slot:actions>

    @include('billing._tabs')

    <x-filter-bar :action="route('quotes.index')" :reset="route('quotes.index')">
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
                 :route="route('quotes.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="[]"
                 bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="number" default>{{ __('Nummer') }}</x-table.th>
                    <th>{{ __('Version') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="valid_until">{{ __('Bindefrist') }}</x-table.th>
                    <x-table.th sort="total" align="right">{{ __('Summe') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($quotes as $quote)
                <tr>
                    <td><a href="{{ route('quotes.show', $quote) }}" class="link">{{ $quote->number }}</a></td>
                    <td>{{ $quote->version }}</td>
                    <td>{{ $quote->customer->name ?? '-' }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$quote->status}") }}</x-status-badge></td>
                    <td>{{ optional($quote->valid_until)->fdate() ?? '—' }}</td>
                    <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $quote->total, 2, withThousandsSeparator: true) }} EUR</td>
                    <td>
                        <x-icon-btn icon="visibility"
                                    :href="route('quotes.show', $quote)"
                                    :label="__('Anzeigen')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">request_quote</span>' :colspan="7" :title="__('Keine Angebote vorhanden')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$quotes" standing />
</x-index-page>
@endsection

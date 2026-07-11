@extends('layouts.app')

@section('title', __('Verleih-Preislisten'))
@section('nav-title', __('Preislisten'))

@section('content')
<x-index-page :subtitle="__('Versionierte Rate Cards (D10): Verleihakten frieren die angewendete Version ein — alte Fälle werden nie umbewertet.')">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @can('create', \App\Models\Rental\RentalRateCard::class)
        <x-card :title="__('Neue Preisliste / neue Version')">
            <form method="POST" action="{{ route('rental.rates.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-input-field name="name" :label="__('Name (gleicher Name = neue Version)')" required />
                <x-input-field name="valid_from" type="date" :label="__('Gültig ab')" />
                <x-input-field name="note" :label="__('Notiz')" />
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Version anlegen') }}</button>
            </form>
        </x-card>
    @endcan

    @foreach ($cards as $card)
        <x-card padding="p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 p-3">
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ $card->name }}</span>
                    <span class="badge badge-outline">v{{ $card->version }}</span>
                    <x-status-badge size="md" outline>{{ $card->status->label() }}</x-status-badge>
                    @if ($card->valid_from !== null)
                        <span class="text-xs text-base-content/60">{{ __('gültig ab') }} {{ $card->valid_from->fdate() }}</span>
                    @endif
                </div>
                @can('update', $card)
                    @if ($card->status === \App\Enums\Rental\RentalRateCardStatus::Draft)
                        <form method="POST" action="{{ route('rental.rates.activate', $card) }}">@csrf
                            <button type="submit" class="btn btn-xs btn-primary">{{ __('Aktivieren') }}</button>
                        </form>
                    @endif
                @endcan
            </div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Art') }}</th>
                        <th>{{ __('Bezeichnung') }}</th>
                        <th>{{ __('Gruppe') }}</th>
                        <th class="text-right">{{ __('Betrag') }}</th>
                        <th>{{ __('Mindestdauer') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($card->items as $item)
                    <tr>
                        <td>{{ $item->kind->label() }}</td>
                        <td>{{ $item->label }}</td>
                        <td>{{ $item->group_code ?? '—' }}</td>
                        <td class="text-right font-mono">{{ number_format((float) $item->amount, 2, ',', '.') }} € / {{ $item->unit }}</td>
                        <td>{{ $item->min_duration_days !== null ? $item->min_duration_days . ' ' . __('Tage') : '—' }}</td>
                        <td class="text-right">
                            @can('update', $card)
                                @if ($card->status === \App\Enums\Rental\RentalRateCardStatus::Draft)
                                    <form method="POST" action="{{ route('rental.rates.items.destroy', [$card, $item]) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Entfernen') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" :title="__('Noch keine Konditionen in dieser Version.')" compact />
                @endforelse
            </x-table>
            @can('update', $card)
                @if ($card->status === \App\Enums\Rental\RentalRateCardStatus::Draft)
                    <form method="POST" action="{{ route('rental.rates.items.store', $card) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 p-3">
                        @csrf
                        <x-select-field name="kind" :label="__('Art')" required>
                            @foreach ($kinds as $kind)
                                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                            @endforeach
                        </x-select-field>
                        <x-input-field name="label" :label="__('Bezeichnung')" required />
                        <x-input-field name="group_code" :label="__('Gruppe (optional)')" />
                        <x-input-field name="amount" type="number" step="0.01" min="0" :label="__('Betrag')" required />
                        <x-input-field name="unit" :label="__('Einheit')" value="day" required />
                        <x-input-field name="min_duration_days" type="number" min="1" :label="__('Mindestdauer (Tage)')" />
                        <button type="submit" class="btn btn-sm">{{ __('Kondition ergänzen') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>
    @endforeach

    <x-pagination :paginator="$cards" standing />
</x-index-page>
@endsection

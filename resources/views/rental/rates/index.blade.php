{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verleih-Preislisten'))
@section('nav-title', __('Preislisten'))

@section('content')
<x-index-page :subtitle="__('Versionierte Rate Cards (D10): Verleihakten frieren die angewendete Version ein — alte Fälle werden nie umbewertet.')">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    @can('create', \App\Models\Rental\RentalRateCard::class)
        <x-card :title="__('Neue Preisliste / neue Version')">
            <form method="POST" action="{{ route('rental.rates.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-filter-field :label="__('Name (gleicher Name = neue Version)')" for="rate-name" show-label>
                    <input type="text" id="rate-name" name="name" required aria-required="true"
                           value="{{ old('name') }}"
                           class="input input-sm input-bordered w-64 @error('name') input-error @enderror">
                </x-filter-field>
                <x-filter-field :label="__('Gültig ab')" for="rate-valid-from" show-label>
                    <input type="date" id="rate-valid-from" name="valid_from"
                           value="{{ old('valid_from') }}"
                           class="input input-sm input-bordered @error('valid_from') input-error @enderror">
                </x-filter-field>
                <x-filter-field :label="__('Notiz')" for="rate-note" show-label>
                    <input type="text" id="rate-note" name="note"
                           value="{{ old('note') }}"
                           class="input input-sm input-bordered w-64 @error('note') input-error @enderror">
                </x-filter-field>
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Version anlegen') }}</x-icon-btn>
            </form>
            @error('name')
                <p class="mt-1 text-error text-sm">{{ $message }}</p>
            @enderror
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
                        <span class="text-xs text-muted">{{ __('gültig ab') }} {{ $card->valid_from->fdate() }}</span>
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
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->amount, 2, withThousandsSeparator: true) }} € / {{ $item->unit }}</td>
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

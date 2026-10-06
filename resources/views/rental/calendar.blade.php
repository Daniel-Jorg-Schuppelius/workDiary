{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verfügbarkeitskalender'))
@section('nav-title', __('Verfügbarkeit'))

@section('content')
<x-index-page :subtitle="__('Belegungsfenster je Gerät: Reservierung, Verleih, Wartung, Reinigung und Transport — inklusive Pufferzeiten.')">
    <x-slot:actions>
        <span class="font-medium">{{ $month->translatedFormat('F Y') }}</span>
    </x-slot:actions>

    @include('rental._tabs')

    <x-filter-bar :action="route('rental.calendar')" :reset="route('rental.calendar')">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <select name="asset_id" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Gerät') }}">
            <option value="">{{ __('Alle Geräte') }}</option>
            @foreach ($assets as $a)
                <option value="{{ $a->sqid }}" @selected($filterAsset === $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
        <select name="group" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Gerätegruppe') }}">
            <option value="">{{ __('Alle Gruppen') }}</option>
            @foreach ($groups as $group)
                <option value="{{ $group }}" @selected($filterGroup === $group)>{{ $group }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-validation-errors />

    <x-month-calendar :month="$month" :items-by-day="$itemsByDay" item-view="rental.partials._calendar_day" />

    {{-- Fenster ohne Akte (Wartung, Reinigung, Transport, Vormerkung) lassen sich nur hier stornieren;
         Rechteprüfung wie im Controller (create auf RentalCase). --}}
    <x-card :title="__('Freie Belegungsfenster')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr><th>{{ __('Gerät') }}</th><th>{{ __('Art') }}</th><th>{{ __('Zeitraum (inkl. Puffer)') }}</th><th>{{ __('Notiz') }}</th><th></th></tr>
            </x-slot:head>
            @forelse ($freeWindows as $window)
                <tr>
                    <td>{{ $window->asset->name ?? '—' }}</td>
                    <td>{{ $window->kind->label() }}</td>
                    <td>{{ $window->blockedFrom()->fdatetime() }} – {{ $window->blockedUntil()->fdatetime() }}</td>
                    <td>{{ $window->note ?? '—' }}</td>
                    <td class="text-right">
                        @can('create', \App\Models\Rental\RentalCase::class)
                            <x-action-form :action="route('rental.reservations.cancel', $window)"
                                           :confirm="__('Das Belegungsfenster wird storniert und das Gerät im Kalender wieder freigegeben.')"
                                           :confirm-label="__('Stornieren')" confirm-icon="event_busy">
                                <x-icon-btn icon="event_busy" size="xs" tone="error" type="submit"
                                            :title="__('Stornieren')" />
                            </x-action-form>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine freien Belegungsfenster im angezeigten Monat.')" compact />
            @endforelse
        </x-table>
    </x-card>

    @can('create', \App\Models\Rental\RentalCase::class)
        <x-card :title="__('Belegungsfenster eintragen (Wartung/Reinigung/Transport/Vormerkung)')">
            <form method="POST" action="{{ route('rental.reservations.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-select-field name="asset_id" :label="__('Gerät')" required>
                    @foreach ($assets as $a)
                        <option value="{{ $a->sqid }}">{{ $a->name }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="kind" :label="__('Art')" required>
                    @foreach (\App\Enums\Rental\RentalReservationKind::cases() as $kind)
                        @if (! in_array($kind, [\App\Enums\Rental\RentalReservationKind::Rental, \App\Enums\Rental\RentalReservationKind::Hard], true))
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endif
                    @endforeach
                </x-select-field>
                <x-input-field name="starts_at" type="datetime-local" :label="__('Beginn')" required />
                <x-input-field name="ends_at" type="datetime-local" :label="__('Ende')" required />
                <x-input-field name="note" :label="__('Notiz')" maxlength="255" />
                <x-button type="submit">{{ __('Eintragen') }}</x-button>
            </form>
        </x-card>
    @endcan
</x-index-page>
@endsection

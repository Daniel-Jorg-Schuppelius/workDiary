{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('dispatch.reservations.title'))
@section('nav-title', __('dispatch.reservations.title'))

@section('content')
    <x-index-page :subtitle="__('dispatch.reservations.subtitle')">
        <x-filter-bar :action="route('vehicle-reservations.index')" submit-label="{{ __('Anwenden') }}">
            <x-filter-field :label="__('dispatch.vehicle.label')" for="res-vehicle">
                <select id="res-vehicle" name="vehicle" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('dispatch.reservations.all_vehicles') }}</option>
                    @foreach ($vehicles as $v)
                        <option value="{{ $v->sqid }}" @selected($vehicle && (int) $vehicle->id === (int) $v->id)>{{ $v->displayName() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('dispatch.vehicle.label') }}</th>
                    <th>{{ __('dispatch.vehicle.from') }}</th>
                    <th>{{ __('dispatch.vehicle.to') }}</th>
                    <th>{{ __('Auftrag') }}</th>
                    <th>{{ __('dispatch.reservations.reserved_by') }}</th>
                    <th>{{ __('Notiz') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($reservations as $reservation)
                <tr>
                    <td><strong>{{ $reservation->vehicle?->displayName() ?? '—' }}</strong></td>
                    <td>{{ $reservation->reserved_from->fdatetime() }}</td>
                    <td>{{ $reservation->reserved_to->fdatetime() }}</td>
                    <td>
                        @if ($reservation->diaryEntry)
                            <a class="link" href="{{ route('diary.show', $reservation->diaryEntry) }}">#{{ $reservation->diaryEntry->id }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $reservation->reservedBy?->name ?? '—' }}</td>
                    <td>{{ $reservation->note ?? '—' }}</td>
                    <td class="text-right">
                        @can('delete', $reservation)
                            <form method="POST" action="{{ route('vehicle-reservations.destroy', $reservation) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('dispatch.vehicle.release') }}</x-button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('dispatch.reservations.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$reservations" standing />
    </x-index-page>
@endsection

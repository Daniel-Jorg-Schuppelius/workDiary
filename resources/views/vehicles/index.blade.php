@extends('layouts.app')

@section('title', __('Fuhrpark'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <a href="{{ route('vehicles.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                        <x-icon name="add" /> {{ __('Neues Fahrzeug') }}
                    </a>
                    <a href="{{ route('vehicles.index', ['archived' => $showArchived ? null : 1]) }}" class="btn btn-sm btn-ghost">
                        @if ($showArchived)
                            {{ __('Aktive zeigen') }}
                        @else
                            {{ __('Archiv zeigen') }}
                        @endif
                    </a>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('vehicles.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'asc'"
                     :sort-params="array_filter(['archived' => $showArchived ? 1 : null], fn ($v) => $v !== null)"
                     bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort="license_plate">{{ __('Kennzeichen') }}</x-table.th>
                        <x-table.th sort="label" default>{{ __('Bezeichnung') }}</x-table.th>
                        <x-table.th sort="type">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort="propulsion">{{ __('Antrieb') }}</x-table.th>
                        <th>{{ __('Standardfahrer') }}</th>
                        <x-table.th sort="rate" align="right">{{ __('Satz €/km') }}</x-table.th>
                        <x-table.th sort="odometer" align="right">{{ __('Tachostand') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($vehicles as $vehicle)
                    <tr class="{{ $vehicle->archived_at ? 'opacity-60' : '' }}">
                        <td class="font-mono">{{ $vehicle->license_plate }}</td>
                        <td>{{ $vehicle->label }}</td>
                        <td><span class="badge badge-ghost badge-sm">{{ __($vehicle->vehicle_type) }}</span></td>
                        <td><span class="badge badge-ghost badge-sm">{{ __($vehicle->propulsion) }}</span></td>
                        <td>{{ $vehicle->defaultUser?->name ?? __('—') }}</td>
                        <td class="text-right">
                            @if ($vehicle->default_rate_per_km !== null)
                                {{ number_format((float) $vehicle->default_rate_per_km, 4, ',', '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">{{ $vehicle->odometer_km !== null ? number_format($vehicle->odometer_km, 0, ',', '.') . ' km' : '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('vehicles.edit', $vehicle) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                            @if ($vehicle->archived_at)
                                <form method="POST" action="{{ route('vehicles.restore', $vehicle) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-ghost">{{ __('Reaktivieren') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Fahrzeug wirklich archivieren?') }}"
                                      data-confirm-label="{{ __('Archivieren') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Archivieren') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">directions_car</span>' :colspan="8" :title="__('Keine Fahrzeuge erfasst')" compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($vehicles->hasPages())
            {{ $vehicles->links() }}
        @endif
    </x-page-shell>
@endsection

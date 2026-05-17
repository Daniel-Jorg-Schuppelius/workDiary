@extends('layouts.app')

@section('title', __('Fuhrpark'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Fuhrpark') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('Fahrzeuge der Organisation') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('vehicles.create') }}" class="btn btn-sm btn-primary">
                    <x-icon name="add" /> {{ __('Neues Fahrzeug') }}
                </a>
                <a href="{{ route('vehicles.index', ['archived' => $showArchived ? null : 1]) }}" class="btn btn-sm btn-ghost">
                    @if ($showArchived)
                        {{ __('Aktive zeigen') }}
                    @else
                        {{ __('Archiv zeigen') }}
                    @endif
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Kennzeichen') }}</th>
                        <th>{{ __('Bezeichnung') }}</th>
                        <th>{{ __('Typ') }}</th>
                        <th>{{ __('Antrieb') }}</th>
                        <th>{{ __('Standardfahrer') }}</th>
                        <th class="text-right">{{ __('Satz €/km') }}</th>
                        <th class="text-right">{{ __('Tachostand') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
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
                                <a href="{{ route('vehicles.edit', $vehicle) }}" class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                @if ($vehicle->archived_at)
                                    <form method="POST" action="{{ route('vehicles.restore', $vehicle) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-ghost">{{ __('Reaktivieren') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" class="inline"
                                          onsubmit="return confirm('{{ __('Fahrzeug wirklich archivieren?') }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Archivieren') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-base-content/60">{{ __('Keine Fahrzeuge erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $vehicles->links() }}
    </div>
@endsection

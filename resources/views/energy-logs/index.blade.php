@extends('layouts.app')

@section('title', __('Tank- & Ladelog'))

@section('content')
    <x-page-shell>


        <x-filter-bar :action="route('energy-logs.index')" :reset="route('energy-logs.index')">
            @if ($selectableUsers)
                <x-filter-field :label="__('Nutzer')" for="energy-user">
                    <select id="energy-user" name="user" class="select select-bordered select-sm" onchange="this.form.submit()">
                        <option value="">{{ __('— eigene —') }}</option>
                        <option value="all" @selected(request('user') === 'all')>{{ __('Alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->id }}" @selected((int) request('user') === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
            <x-filter-field :label="__('Fahrzeug')" for="energy-vehicle">
                <select id="energy-vehicle" name="vehicle" class="select select-bordered select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Alle Fahrzeuge') }}</option>
                    @foreach ($vehicles as $v)
                        <option value="{{ $v->id }}" @selected($selectedVehicleId === (int) $v->id)>{{ $v->displayName() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @foreach (request()->except(['user', 'vehicle']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <x-slot:extra>
                <a href="{{ route('energy-logs.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                    <x-icon name="add" /> {{ __('Neuer Eintrag') }}
                </a>
            </x-slot:extra>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Liter gesamt') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['liters'], 2, ',', '.') }} l</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('kWh gesamt') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['kwh'], 2, ',', '.') }} kWh</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Kosten') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['cost'], 2, ',', '.') }} €</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Strecke (Δ)') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['distance'], 0, ',', '.') }} km</div>
            </div>
        </div>

        <x-card padding="p-0">
            <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Fahrzeug') }}</th>
                        <th>{{ __('Nutzer') }}</th>
                        <th>{{ __('Typ') }}</th>
                        <th class="text-right">{{ __('Menge') }}</th>
                        <th class="text-right">{{ __('Kosten') }}</th>
                        <th class="text-right">{{ __('Tacho') }}</th>
                        <th class="text-right">{{ __('Δ km') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->started_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $log->vehicle?->displayName() }}</td>
                            <td>{{ $log->user?->name }}</td>
                            <td>
                                <span class="badge badge-sm">{{ __($log->energy_type) }}</span>
                                @if ($log->fuel_kind)
                                    <span class="badge badge-ghost badge-sm">{{ __($log->fuel_kind) }}</span>
                                @endif
                                @if ($log->charger_type)
                                    <span class="badge badge-ghost badge-sm">{{ __($log->charger_type) }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format((float) $log->quantity, 2, ',', '.') }} {{ $log->unit === 'kwh' ? 'kWh' : 'l' }}</td>
                            <td class="text-right">{{ $log->cost_total !== null ? number_format((float) $log->cost_total, 2, ',', '.') . ' €' : '—' }}</td>
                            <td class="text-right">{{ $log->odometer_km !== null ? number_format($log->odometer_km, 0, ',', '.') : '—' }}</td>
                            <td class="text-right">{{ $log->distance_since_last !== null ? number_format($log->distance_since_last, 0, ',', '.') : '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('energy-logs.edit', $log) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('energy-logs.destroy', $log) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Eintrag wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-0"><x-empty-state :compact="true" :title="__('Keine Einträge im gewählten Zeitraum')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </x-card>

        {{ $logs->links() }}
    </x-page-shell>
@endsection

{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('passenger.rides.title'))
@section('nav-title', __('passenger.rides.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('passenger.rides.subtitle')">
    <x-slot:actions>
        @can('create', \App\Models\Passenger\PassengerRide::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('passenger-rides.create')"
                        show-label>{{ __('passenger.rides.action.create') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="payments" size="sm" :href="route('passenger-settlements.index')" show-label>{{ __('passenger.settlements.title') }}</x-icon-btn>
        <x-icon-btn icon="tune" size="sm" :href="route('passenger-masterdata.index')" show-label>{{ __('passenger.masterdata.title') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('passenger.rides.kpi.open')" :value="$openCount" />
        <x-kpi-tile :label="__('passenger.rides.kpi.return_proof_missing')" :value="$returnProofCount" />
    </div>

    <x-filter-bar :action="route('passenger-rides.index')" :reset="route('passenger-rides.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Passenger\RideStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="mode" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('passenger.field.operation_mode') }}">
            <option value="">{{ __('passenger.rides.filter.all_modes') }}</option>
            @foreach (\App\Enums\Passenger\RideOperationMode::cases() as $m)
                <option value="{{ $m->value }}" @selected(request('mode') === $m->value)>{{ $m->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('passenger.field.requested_at') }}</th>
                    <th>{{ __('passenger.field.operation_mode') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('passenger.field.pickup_address') }}</th>
                    <th>{{ __('passenger.field.driver') }}</th>
                    <th>{{ __('passenger.field.vehicle') }}</th>
                    <th class="text-right">{{ __('passenger.field.planned_net') }}</th>
                    <th class="text-right">{{ __('passenger.field.meter_net') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($rides as $ride)
                <tr>
                    <td class="whitespace-nowrap">{{ optional($ride->requested_at)->fdatetime() ?? '—' }}</td>
                    <td>{{ $ride->operation_mode->label() }}</td>
                    <td>
                        <x-status-badge size="md" outline :tone="$ride->status->tone()">{{ $ride->status->label() }}</x-status-badge>
                        @if ($ride->awaitsReturnProof())
                            <x-status-badge size="md" outline tone="warning">{{ __('passenger.badge.return_open') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="max-w-64 truncate">{{ $ride->pickup_address ?? '—' }}</td>
                    <td>{{ $ride->driver->name ?? '—' }}</td>
                    <td>{{ $ride->vehicle->license_plate ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $ride->planned_net !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $ride->planned_net, 2, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right tabular-nums">
                        {{ $ride->meter_net !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $ride->meter_net, 2, withThousandsSeparator: true) : '—' }}
                        @if ($ride->hasFareDeviation())
                            <span class="text-warning" title="{{ __('passenger.badge.fare_deviation') }}">Δ {{ $ride->fareDeviation() }}</span>
                        @endif
                    </td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('passenger-rides.show', $ride)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">local_taxi</span>' :colspan="9" :title="__('passenger.rides.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$rides" standing />
</x-index-page>
@endsection

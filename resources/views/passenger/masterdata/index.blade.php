{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('passenger.masterdata.title'))
@section('nav-title', __('passenger.masterdata.title'))

@section('content')
<x-index-page :subtitle="__('passenger.masterdata.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="local_taxi" size="sm" :href="route('passenger-rides.index')" show-label>{{ __('passenger.rides.title') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-card :title="__('passenger.masterdata.tariffs')" padding="p-0">
        <x-slot:actions>
            @can('create', \App\Models\Passenger\PassengerFareTariff::class)
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('passenger-masterdata.tariffs.create')"
                            show-label>{{ __('passenger.masterdata.action.create_tariff') }}</x-icon-btn>
            @endcan
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('passenger.field.name') }}</th>
                    <th>{{ __('passenger.field.tariff_area') }}</th>
                    <th>{{ __('passenger.field.operation_mode') }}</th>
                    <th>{{ __('passenger.field.valid_from') }}</th>
                    <th class="text-right">{{ __('passenger.field.base_price') }}</th>
                    <th class="text-right">{{ __('passenger.field.price_per_km') }}</th>
                    <th class="text-right">{{ __('passenger.field.rules_count') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($tariffs as $tariff)
                <tr>
                    <td>{{ $tariff->name }}</td>
                    <td>{{ $tariff->tariff_area ?? '—' }}</td>
                    <td>{{ $tariff->operation_mode->label() }}</td>
                    <td class="whitespace-nowrap">{{ $tariff->valid_from->fdate() }}{{ $tariff->valid_until !== null ? ' – ' . $tariff->valid_until->fdate() : '' }}</td>
                    <td class="text-right tabular-nums">{{ $tariff->base_price }}</td>
                    <td class="text-right tabular-nums">{{ $tariff->price_per_km }}</td>
                    <td class="text-right tabular-nums">
                        <a href="{{ route('passenger-masterdata.index', ['tariff' => $tariff->sqid]) }}" class="link">{{ $tariff->rules_count }}</a>
                    </td>
                    <td>
                        <x-status-badge size="md" outline :tone="$tariff->active ? 'success' : 'neutral'">
                            {{ $tariff->active ? __('passenger.badge.active') : __('passenger.badge.inactive') }}
                        </x-status-badge>
                    </td>
                    <td class="text-right">
                        @can('update', $tariff)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('passenger-masterdata.tariffs.edit', $tariff)" :label="__('passenger.masterdata.action.edit')" />
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">price_change</span>' :colspan="9" :title="__('passenger.masterdata.tariffs_empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($selectedTariff !== null)
        <x-card :title="__('passenger.masterdata.rules_for', ['tariff' => $selectedTariff->name])">
            <x-table size="sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('passenger.field.code') }}</th>
                        <th>{{ __('passenger.field.label') }}</th>
                        <th>{{ __('passenger.field.kind') }}</th>
                        <th class="text-right">{{ __('passenger.field.amount') }}</th>
                        <th class="text-right">{{ __('passenger.field.percent') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($selectedTariff->rules as $rule)
                    <tr>
                        <td class="font-mono">{{ $rule->code }}</td>
                        <td>{{ $rule->label }}</td>
                        <td>{{ __('passenger.rule_kind.' . $rule->kind) }}</td>
                        <td class="text-right tabular-nums">{{ $rule->amount ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $rule->percent ?? '—' }}</td>
                        <td class="text-right">
                            @can('update', $selectedTariff)
                                <form method="POST" action="{{ route('passenger-masterdata.tariffs.rules.destroy', [$selectedTariff, $rule]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="delete" type="submit" tone="error" :label="__('passenger.masterdata.action.remove_rule')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">percent</span>' :colspan="6" :title="__('passenger.masterdata.rules_empty')" compact />
                @endforelse
            </x-table>

            @can('update', $selectedTariff)
                <form method="POST" action="{{ route('passenger-masterdata.tariffs.rules.store', $selectedTariff) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                    @csrf
                    <input type="text" name="code" placeholder="{{ __('passenger.field.code') }} *" class="input input-sm input-bordered w-28" required aria-label="{{ __('passenger.field.code') }}">
                    <input type="text" name="label" placeholder="{{ __('passenger.field.label') }} *" class="input input-sm input-bordered w-48" required aria-label="{{ __('passenger.field.label') }}">
                    <select name="kind" class="select select-sm select-bordered" required aria-label="{{ __('passenger.field.kind') }}">
                        <option value="surcharge">{{ __('passenger.rule_kind.surcharge') }}</option>
                        <option value="discount">{{ __('passenger.rule_kind.discount') }}</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="{{ __('passenger.field.amount') }}" class="input input-sm input-bordered w-28" aria-label="{{ __('passenger.field.amount') }}">
                    <input type="number" step="0.1" min="0" max="100" name="percent" placeholder="{{ __('passenger.field.percent') }}" class="input input-sm input-bordered w-28" aria-label="{{ __('passenger.field.percent') }}">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('passenger.masterdata.action.add_rule') }}</button>
                </form>
            @endcan
        </x-card>
    @endif

    <x-card :title="__('passenger.masterdata.concessions')" padding="p-0">
        <x-slot:actions>
            @can('create', \App\Models\Passenger\PassengerConcession::class)
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('passenger-masterdata.concessions.create')"
                            show-label>{{ __('passenger.masterdata.action.create_concession') }}</x-icon-btn>
            @endcan
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('passenger.field.operation_mode') }}</th>
                    <th>{{ __('passenger.field.authority') }}</th>
                    <th>{{ __('passenger.field.reference_no') }}</th>
                    <th>{{ __('passenger.field.tariff_area') }}</th>
                    <th>{{ __('passenger.field.valid_until') }}</th>
                    <th class="text-right">{{ __('passenger.field.licensed_vehicles') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($concessions as $concession)
                <tr>
                    <td>{{ $concession->operation_mode->label() }}</td>
                    <td>{{ $concession->authority }}</td>
                    <td class="font-mono">{{ $concession->reference_no }}</td>
                    <td>{{ $concession->tariff_area ?? '—' }}</td>
                    <td>
                        @if ($concession->valid_until !== null && $concession->valid_until->isPast())
                            <span class="text-error font-medium">{{ $concession->valid_until->fdate() }}</span>
                        @else
                            {{ optional($concession->valid_until)->fdate() ?? '—' }}
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $concession->licensed_vehicles ?? '—' }}</td>
                    <td>
                        <x-status-badge size="md" outline :tone="$concession->isValidOn() ? 'success' : 'error'">
                            {{ $concession->isValidOn() ? __('passenger.badge.valid') : __('passenger.badge.invalid') }}
                        </x-status-badge>
                    </td>
                    <td class="text-right">
                        @can('update', $concession)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('passenger-masterdata.concessions.edit', $concession)" :label="__('passenger.masterdata.action.edit')" />
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">verified</span>' :colspan="8" :title="__('passenger.masterdata.concessions_empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('passenger.masterdata.vehicle_profiles')" padding="p-0">
        <x-slot:actions>
            @can('create', \App\Models\Passenger\PassengerVehicleProfile::class)
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('passenger-masterdata.vehicle-profiles.create')"
                            show-label>{{ __('passenger.masterdata.action.create_vehicle_profile') }}</x-icon-btn>
            @endcan
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('passenger.field.vehicle') }}</th>
                    <th>{{ __('passenger.field.order_number') }}</th>
                    <th>{{ __('passenger.field.operation_modes') }}</th>
                    <th class="text-right">{{ __('passenger.field.passenger_seats') }}</th>
                    <th>{{ __('passenger.field.meter_kind') }}</th>
                    <th>{{ __('passenger.field.proofs') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($profiles as $profile)
                <tr>
                    <td>{{ $profile->vehicle->license_plate ?? '—' }}</td>
                    <td class="font-mono">{{ $profile->order_number ?? '—' }}</td>
                    <td>{{ collect($profile->operation_modes)->map(fn($m) => \App\Enums\Passenger\RideOperationMode::tryFrom($m)?->label())->filter()->implode(', ') }}</td>
                    <td class="text-right tabular-nums">{{ $profile->passenger_seats ?? '—' }}</td>
                    <td>{{ $profile->meter_kind !== null ? __('passenger.meter.' . $profile->meter_kind) : '—' }}</td>
                    <td>
                        @php $expired = $profile->expiredProofs(); @endphp
                        <x-status-badge size="md" outline :tone="$expired === [] ? 'success' : 'error'">
                            {{ $expired === [] ? __('passenger.badge.proofs_valid') : __('passenger.badge.proofs_expired') }}
                        </x-status-badge>
                    </td>
                    <td class="text-right">
                        @can('update', $profile)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('passenger-masterdata.vehicle-profiles.edit', $profile)" :label="__('passenger.masterdata.action.edit')" />
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">directions_car</span>' :colspan="7" :title="__('passenger.masterdata.vehicle_profiles_empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection

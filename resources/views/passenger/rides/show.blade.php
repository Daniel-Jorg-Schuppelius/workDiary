{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('passenger.rides.detail_title'))
@section('nav-title', __('passenger.rides.title'))

@section('content')
@php
    use App\Enums\Passenger\{RidePriceKind, RideStatus};
    $status = $ride->status;
    $fmt = fn($v) => $v !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) : '—';
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('passenger.rides.detail_title') . ' — ' . $ride->operation_mode->label()">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline :tone="$status->tone()">{{ $status->label() }}</x-status-badge>
                <x-status-badge size="md" outline>{{ $ride->order_channel->label() }}</x-status-badge>
                @if ($ride->hasFareDeviation())
                    <x-status-badge size="md" outline tone="warning">{{ __('passenger.badge.fare_deviation') }}: {{ $ride->fareDeviation() }}</x-status-badge>
                @endif
                @if ($ride->awaitsReturnProof())
                    <x-status-badge size="md" outline tone="warning">{{ __('passenger.badge.return_open') }}</x-status-badge>
                @endif
            </div>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('passenger-rides.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('passenger.section.order')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('passenger.field.operation_mode')">{{ $ride->operation_mode->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.order_channel')">{{ $ride->order_channel->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.requested_at')">{{ optional($ride->requested_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.accepted_at')">{{ optional($ride->accepted_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.order_received_at')">{{ optional($ride->order_received_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.order_receipt_reference')">{{ $ride->order_receipt_reference }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.mediator_reference')">{{ $ride->mediator_reference }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kunde')">{{ $ride->diaryEntry->customer->name ?? null }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>

        <x-card :title="__('passenger.section.route')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('passenger.field.pickup_address')">{{ $ride->pickup_address ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.destination_address')">
                    {{ $ride->destination_address ?? ($ride->destination_open ? __('passenger.field.destination_open') : '—') }}
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.window_start')">{{ optional($ride->window_start)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.window_end')">{{ optional($ride->window_end)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.passenger_count')">{{ $ride->passenger_count }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.luggage_count')">{{ $ride->luggage_count }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.requirements')">
                    {{ collect([
                        $ride->wheelchair ? __('passenger.field.wheelchair') : null,
                        $ride->barrier_free_required ? __('passenger.field.barrier_free_required') : null,
                        $ride->animal ? __('passenger.field.animal') : null,
                        $ride->child_seats > 0 ? __('passenger.field.child_seats') . ': ' . $ride->child_seats : null,
                    ])->filter()->implode(', ') ?: '—' }}
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.passenger_name')">{{ $ride->passenger_name }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.passenger_contact')">{{ $ride->passenger_contact }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>

        <x-card :title="__('passenger.section.dispatch')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('passenger.field.driver')">{{ $ride->driver->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.vehicle')">{{ $ride->vehicle->license_plate ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.concession')">{{ $ride->concession?->reference_no }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.assigned_at')">{{ optional($ride->assigned_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.meter_serial')">{{ data_get($ride->assignment_snapshot, 'vehicle.meter_serial') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.tse_reference')">{{ data_get($ride->assignment_snapshot, 'vehicle.tse_reference') }}</x-detail-grid.row>
            </x-detail-grid>

            @can('update', $ride)
                @if ($status === RideStatus::Accepted)
                    <form method="POST" action="{{ route('passenger-rides.assign', $ride) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @csrf
                        <x-user-select name="driver_user_id" :users="$drivers" value-key="sqid" :selected="old('driver_user_id')" required />
                        <select name="vehicle_id" class="select select-sm select-bordered" required aria-label="{{ __('passenger.field.vehicle') }}">
                            <option value="">{{ __('passenger.field.vehicle') }} …</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->sqid }}" @selected((string) old('vehicle_id') === $vehicle->sqid)>{{ $vehicle->license_plate }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary col-span-2">{{ __('passenger.rides.action.assign') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('passenger.section.pricing')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('passenger.field.price_kind')">{{ $ride->price_kind?->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.tariff')">{{ data_get($ride->fare_snapshot, 'name') ?? $ride->tariff?->name }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.planned_net')">{{ $fmt($ride->planned_net) }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.meter_net')">{{ $fmt($ride->meter_net) }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.tax_rate')">{{ $ride->tax_rate !== null ? $ride->tax_rate . ' %' : null }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.gross_amount')">{{ $fmt($ride->gross_amount) }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.payment_method')">{{ $ride->payment_method }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.settlement')">{{ $ride->shiftSettlement !== null ? $ride->shiftSettlement->shift_date->fdate() : null }}</x-detail-grid.row>
            </x-detail-grid>

            @can('update', $ride)
                @if ($status === RideStatus::Assigned)
                    <form method="POST" action="{{ route('passenger-rides.start', $ride) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @csrf
                        <select name="price_kind" class="select select-sm select-bordered" required aria-label="{{ __('passenger.field.price_kind') }}">
                            @foreach (RidePriceKind::cases() as $kind)
                                <option value="{{ $kind->value }}" @selected(old('price_kind') === $kind->value)>{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                        <select name="tariff_id" class="select select-sm select-bordered" aria-label="{{ __('passenger.field.tariff') }}">
                            <option value="">{{ __('passenger.field.without_tariff') }}</option>
                            @foreach ($tariffs as $tariff)
                                <option value="{{ $tariff->sqid }}" @selected((string) old('tariff_id') === $tariff->sqid)>{{ $tariff->name }} ({{ $tariff->tariff_area ?? '—' }})</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.1" min="0" name="estimated_km" value="{{ old('estimated_km') }}" placeholder="{{ __('passenger.field.estimated_km') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.estimated_km') }}">
                        <input type="number" step="1" min="0" name="estimated_minutes" value="{{ old('estimated_minutes') }}" placeholder="{{ __('passenger.field.estimated_minutes') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.estimated_minutes') }}">
                        <input type="number" step="0.01" min="0" name="planned_net" value="{{ old('planned_net') }}" placeholder="{{ __('passenger.field.planned_net_optional') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.planned_net_optional') }}">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('passenger.rides.action.start') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('passenger.section.progress')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('passenger.field.pickup_started_at')">{{ optional($ride->pickup_started_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.waiting_started_at')">{{ optional($ride->waiting_started_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.picked_up_at')">{{ optional($ride->picked_up_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.completed_at')">{{ optional($ride->completed_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.occupied_km')">{{ $ride->occupied_km }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.empty_km')">{{ $ride->empty_km }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.closing_reason')">{{ $ride->closing_reason }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('passenger.field.closing_note')">{{ $ride->closing_note }}</x-detail-grid.row>
            </x-detail-grid>

            @can('update', $ride)
                <div class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                    @foreach ([RideStatus::Waiting, RideStatus::Occupied] as $target)
                        @if ($status->canTransitionTo($target))
                            <form method="POST" action="{{ route('passenger-rides.transition', $ride) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $target->value }}">
                                <button type="submit" class="btn btn-sm">{{ __('passenger.rides.action.transition_to', ['status' => $target->label()]) }}</button>
                            </form>
                        @endif
                    @endforeach
                </div>

                @if ($status === RideStatus::Occupied)
                    <form method="POST" action="{{ route('passenger-rides.complete', $ride) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @csrf
                        <input type="number" step="0.01" min="0" name="meter_net" value="{{ old('meter_net') }}" placeholder="{{ __('passenger.field.meter_net') }} *" class="input input-sm input-bordered" required aria-label="{{ __('passenger.field.meter_net') }}">
                        <input type="number" step="0.001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', '7') }}" placeholder="{{ __('passenger.field.tax_rate') }} *" class="input input-sm input-bordered" required aria-label="{{ __('passenger.field.tax_rate') }}">
                        <select name="payment_method" class="select select-sm select-bordered" required aria-label="{{ __('passenger.field.payment_method') }}">
                            @foreach (['bar', 'karte', 'gutschein', 'rechnung', 'vermittler'] as $method)
                                <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('passenger.payment.' . $method) }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.1" min="0" name="occupied_km" value="{{ old('occupied_km') }}" placeholder="{{ __('passenger.field.occupied_km') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.occupied_km') }}">
                        <input type="number" step="0.1" min="0" name="empty_km" value="{{ old('empty_km') }}" placeholder="{{ __('passenger.field.empty_km') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.empty_km') }}">
                        <input type="number" step="1" min="0" name="odometer_end_km" value="{{ old('odometer_end_km') }}" placeholder="{{ __('passenger.field.odometer_end_km') }}" class="input input-sm input-bordered" aria-label="{{ __('passenger.field.odometer_end_km') }}">
                        <button type="submit" class="btn btn-sm btn-primary col-span-2">{{ __('passenger.rides.action.complete') }}</button>
                    </form>
                @endif

                @php
                    $closeTargets = collect([RideStatus::Cancelled, RideStatus::NoShow, RideStatus::Aborted])
                        ->filter(fn(RideStatus $t) => $status->canTransitionTo($t));
                @endphp
                @if ($closeTargets->isNotEmpty())
                    <form method="POST" action="{{ route('passenger-rides.close', $ride) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="status" class="select select-sm select-bordered" required aria-label="{{ __('Status') }}">
                            @foreach ($closeTargets as $target)
                                <option value="{{ $target->value }}">{{ $target->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="reason" value="{{ old('reason') }}" placeholder="{{ __('passenger.field.reason') }} *" class="input input-sm input-bordered w-64" required aria-label="{{ __('passenger.field.reason') }}">
                        <button type="submit" class="btn btn-sm btn-outline btn-error">{{ __('passenger.rides.action.close') }}</button>
                    </form>
                @endif

                @if ($ride->awaitsReturnProof())
                    <form method="POST" action="{{ route('passenger-rides.return', $ride) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="follow_up_ride_id" class="select select-sm select-bordered" aria-label="{{ __('passenger.field.follow_up_ride') }}">
                            <option value="">{{ __('passenger.field.return_to_base') }}</option>
                            @foreach ($followUpCandidates as $candidate)
                                <option value="{{ $candidate->sqid }}">{{ __('passenger.field.follow_up_ride') }} #{{ $candidate->sqid }} — {{ optional($candidate->requested_at)->fdatetime() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm">{{ __('passenger.rides.action.record_return') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>
    </div>
</x-page-shell>
@endsection

@extends('layouts.app')

@section('title', __('passenger.settlements.title'))
@section('nav-title', __('passenger.settlements.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('passenger.settlements.subtitle')">
    <x-slot:actions>
        @can('settle', \App\Models\Passenger\PassengerShiftSettlement::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('passenger-settlements.create')"
                        show-label>{{ __('passenger.settlements.action.create') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="local_taxi" size="sm" :href="route('passenger-rides.index')" show-label>{{ __('passenger.rides.title') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('passenger.settlements.kpi.open')" :value="$openCount" />
    </div>

    <x-filter-bar :action="route('passenger-settlements.index')" :reset="route('passenger-settlements.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (['open', 'balanced', 'disputed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('passenger.settlement_status.' . $s) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('passenger.field.shift_date') }}</th>
                    <th>{{ __('passenger.field.driver') }}</th>
                    <th>{{ __('passenger.field.vehicle') }}</th>
                    <th class="text-right">{{ __('passenger.field.meter_total') }}</th>
                    <th class="text-right">{{ __('passenger.field.payment_total') }}</th>
                    <th class="text-right">{{ __('passenger.field.tip_total') }}</th>
                    <th class="text-right">{{ __('passenger.field.difference') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($settlements as $settlement)
                @php $difference = $settlement->computeDifference(); @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ $settlement->shift_date->fdate() }}</td>
                    <td>{{ $settlement->driver->name ?? '—' }}</td>
                    <td>{{ $settlement->vehicle->license_plate ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $settlement->meter_total }}</td>
                    <td class="text-right tabular-nums">{{ $settlement->paymentTotal() }}</td>
                    <td class="text-right tabular-nums">{{ $settlement->tip_total }}</td>
                    <td class="text-right tabular-nums {{ bccomp($difference, '0', 2) !== 0 ? 'text-warning font-medium' : '' }}">{{ $difference }}</td>
                    <td>
                        <x-status-badge size="md" outline :tone="match ($settlement->status) {
                            \App\Models\Passenger\PassengerShiftSettlement::STATUS_BALANCED => 'success',
                            \App\Models\Passenger\PassengerShiftSettlement::STATUS_DISPUTED => 'warning',
                            default => 'neutral',
                        }">{{ __('passenger.settlement_status.' . $settlement->status) }}</x-status-badge>
                    </td>
                    <td class="text-right">
                        @can('settle', $settlement)
                            @if ($settlement->status === \App\Models\Passenger\PassengerShiftSettlement::STATUS_OPEN)
                                <div class="flex items-center justify-end gap-1">
                                    <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('passenger-settlements.edit', $settlement)" :label="__('passenger.masterdata.action.edit')" />
                                    <form method="POST" action="{{ route('passenger-settlements.close', $settlement) }}" class="flex items-center gap-1">
                                        @csrf
                                        @if (! $settlement->isBalanced())
                                            <input type="text" name="difference_reason" placeholder="{{ __('passenger.field.difference_reason') }} *" class="input input-xs input-bordered w-44" aria-label="{{ __('passenger.field.difference_reason') }}">
                                        @endif
                                        <x-icon-btn icon="task_alt" type="submit" :label="__('passenger.settlements.action.close')" />
                                    </form>
                                </div>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">payments</span>' :colspan="9" :title="__('passenger.settlements.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$settlements" standing />
</x-index-page>
@endsection

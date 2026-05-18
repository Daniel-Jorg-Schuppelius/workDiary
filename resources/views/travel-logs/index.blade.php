@extends('layouts.app')

@section('title', __('Fahrtenbuch'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <x-icon-btn icon="download" size="sm"
                                :href="route('travel-logs.export', array_merge(request()->query(), ['from' => $from->toDateString(), 'to' => $to->toDateString()]))"
                                show-label>{{ __('CSV-Export') }}</x-icon-btn>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('travel-logs.create')"
                                show-label>{{ __('Neue Fahrt') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>


        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('Gefahrene Kilometer')" :value="number_format($totals['distance_km'], 2, ',', '.') . ' km'" />
            <x-kpi-tile :label="__('Erstattung')" :value="number_format($totals['reimbursement'], 2, ',', '.') . ' €'" />
        </div>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('travel-logs.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['from' => $from->toDateString(), 'to' => $to->toDateString()]"
                     bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                        <x-table.th sort="from">{{ __('Von') }}</x-table.th>
                        <x-table.th sort="to">{{ __('Nach') }}</x-table.th>
                        <x-table.th sort="distance" align="right">{{ __('km') }}</x-table.th>
                        <x-table.th sort="vehicle">{{ __('Fahrzeug') }}</x-table.th>
                        <x-table.th sort="reimbursement" align="right">{{ __('Erstattung') }}</x-table.th>
                        <x-table.th sort="purpose">{{ __('Zweck') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->date?->format('d.m.Y') }}</td>
                        <td>{{ $log->from_address }}</td>
                        <td>{{ $log->to_address }}</td>
                        <td class="text-right">
                            {{ number_format((float) $log->distance_km, 2, ',', '.') }}
                            @if ($log->round_trip)
                                <span class="badge badge-ghost badge-xs ml-1">{{ __('hin/rück') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-ghost badge-sm">{{ __($log->vehicle) }}</span>
                        </td>
                        <td class="text-right">
                            {{ number_format((float) $log->reimbursement_total, 2, ',', '.') }} €
                        </td>
                        <td class="max-w-xs truncate">{{ $log->purpose }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('travel-logs.edit', $log)"
                                        :label="__('Bearbeiten')" />
                            <form method="POST" action="{{ route('travel-logs.destroy', $log) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Fahrt wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">directions_car</span>' :colspan="8" :title="__('Keine Fahrten im gewählten Zeitraum')" compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($logs->hasPages())
            {{ $logs->links() }}
        @endif
    </x-page-shell>
@endsection

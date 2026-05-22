@extends('layouts.app')

@section('title', __('Verpflegungspauschalen'))
@section('nav-title', __('Verpflegungspauschalen'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <form method="GET" action="{{ route('per-diem-trips.index') }}" class="flex items-center gap-1">
                        <label for="pd-status" class="sr-only">{{ __('Status') }}</label>
                        <select id="pd-status" name="status"
                                class="select select-bordered select-sm"
                                onchange="this.form.submit()">
                            <option value="">{{ __('Alle Status') }}</option>
                            @foreach ($statusOptions as $opt)
                                <option value="{{ $opt->value }}" @selected($statusFilter === $opt->value)>{{ $opt->label() }}</option>
                            @endforeach
                        </select>
                        @if ($statusFilter !== '')
                            <x-icon-btn icon="restart_alt" tone="ghost" size="sm"
                                        :href="route('per-diem-trips.index')"
                                        :label="__('Filter zurücksetzen')" />
                        @endif
                    </form>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('per-diem-trips.create')"
                                show-label>{{ __('Neue Reise') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-kpi-tile :label="__('Reisen im Zeitraum')" :value="(string) $totals['count']" />
            <x-kpi-tile :label="__('Pauschalen-Summe')"
                        :value="number_format($totals['amount'], 2, ',', '.') . ' €'" />
            <x-kpi-tile :label="__('Offene Reisen')"
                        :value="(string) $totals['open']"
                        :tone="$totals['open'] > 0 ? 'warning' : 'ghost'" />
        </div>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('per-diem-trips.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['status' => $statusFilter]"
                     bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort="started_at" default>{{ __('Beginn') }}</x-table.th>
                        <x-table.th>{{ __('Ende') }}</x-table.th>
                        <x-table.th sort="location">{{ __('Ort') }}</x-table.th>
                        <x-table.th>{{ __('Zweck') }}</x-table.th>
                        <x-table.th align="right">{{ __('Pauschale') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($trips as $trip)
                    <tr>
                        <td class="whitespace-nowrap">{{ $trip->started_at->format('d.m.Y H:i') }}</td>
                        <td class="whitespace-nowrap">{{ $trip->ended_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="place" class="text-info" />
                                {{ $trip->location }}
                                <span class="text-xs text-base-content/60">({{ $trip->country }})</span>
                            </span>
                        </td>
                        <td class="max-w-xs truncate">{{ $trip->purpose }}</td>
                        <td class="text-right whitespace-nowrap">
                            {{ number_format((float) $trip->totalAmount(), 2, ',', '.') }} €
                            <span class="text-xs text-base-content/60 ml-1">({{ $trip->days->count() }} {{ __('Tage') }})</span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $trip->status->tone() }} badge-sm">
                                {{ $trip->status->label() }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="visibility"
                                        :href="route('per-diem-trips.show', $trip)"
                                        :label="__('Anzeigen')" />
                            @can('update', $trip)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('per-diem-trips.edit', $trip)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $trip)
                                <form method="POST" action="{{ route('per-diem-trips.destroy', $trip) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Reise wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">restaurant_menu</span>'
                                   :colspan="7"
                                   :title="__('Keine Reisen im gewählten Zeitraum')"
                                   compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($trips->hasPages())
            {{ $trips->links() }}
        @endif
    </x-page-shell>
@endsection

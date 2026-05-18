@extends('layouts.app')

@section('title', __('Touren'))

@section('content')
    <x-page-shell>


        <x-filter-bar :action="route('tours.index')" :reset="route('tours.index')">
            <x-filter-field :label="__('Status')" for="tours-status">
                <select id="tours-status" name="status" class="select select-bordered select-sm">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" @selected($selectedStatus === $st)>{{ __($st) }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($selectableUsers !== null)
                <x-filter-field :label="__('Fahrer')" for="tours-user">
                    <select id="tours-user" name="user" class="select select-bordered select-sm">
                        <option value="all">{{ __('alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->id }}" @selected($targetUser?->id === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
            <x-slot:extra>
                <a href="{{ route('tours.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                    <x-icon name="add" /> {{ __('Neue Tour') }}
                </a>
            </x-slot:extra>
        </x-filter-bar>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('tours.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="array_filter([
                         'from' => $from->toDateString(),
                         'to' => $to->toDateString(),
                         'user' => request('user'),
                         'status' => $selectedStatus,
                     ], fn ($v) => $v !== null && $v !== '')"
                     bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort="tour_date" default>{{ __('Datum') }}</x-table.th>
                        <th>{{ __('Fahrer') }}</th>
                        <th>{{ __('Fahrzeug') }}</th>
                        <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                        <x-table.th sort="distance" align="right">{{ __('km') }}</x-table.th>
                        <x-table.th sort="duration" align="right">{{ __('min') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($tours as $tour)
                    <tr>
                        <td>{{ $tour->tour_date?->format('d.m.Y') }}</td>
                        <td>{{ $tour->user?->name }}</td>
                        <td>{{ $tour->vehicle?->license_plate ?? '—' }}</td>
                        <td>
                            <a href="{{ route('tours.show', $tour) }}" class="link">
                                {{ $tour->name ?? ('#' . $tour->id) }}
                            </a>
                        </td>
                        <td class="text-right">{{ number_format((float) $tour->planned_distance_km, 2, ',', '.') }}</td>
                        <td class="text-right">{{ $tour->planned_duration_minutes }}</td>
                        <td><span class="badge badge-ghost badge-sm">{{ __($tour->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('tours.edit', $tour) }}" class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                            <form method="POST" action="{{ route('tours.destroy', $tour) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Tour wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">route</span>' :colspan="8" :title="__('Keine Touren im gewählten Zeitraum')" compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($tours->hasPages())
            {{ $tours->links() }}
        @endif
    </x-page-shell>
@endsection

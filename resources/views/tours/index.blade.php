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
            <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Fahrer') }}</th>
                        <th>{{ __('Fahrzeug') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th class="text-right">{{ __('km') }}</th>
                        <th class="text-right">{{ __('min') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr><td colspan="8" class="p-0"><x-empty-state :compact="true" :title="__('Keine Touren im gewählten Zeitraum')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </x-card>

        {{ $tours->links() }}
    </x-page-shell>
@endsection

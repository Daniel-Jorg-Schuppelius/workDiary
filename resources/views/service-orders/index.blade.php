@extends('layouts.app')

@section('title', __('Aufträge'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :title="__('Service-Aufträge')"
                            :subtitle="$from->format('d.m.Y') . ' – ' . $to->format('d.m.Y')">
                <x-slot:actions>
                    <a href="{{ route('service-orders.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                        <x-icon name="add" /> {{ __('Neuer Auftrag') }}
                    </a>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-card>
            <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="label-text">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered select-sm">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" @selected($selectedStatus === $st)>{{ __($st) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($selectableUsers !== null)
                <div>
                    <label class="label-text">{{ __('Mitarbeiter') }}</label>
                    <select name="user" class="select select-bordered select-sm">
                        <option value="all">{{ __('alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->id }}" @selected($targetUser?->id === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button class="btn btn-sm">{{ __('Filtern') }}</button>
            </form>
        </x-card>

        <x-card padding="p-0">
            <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Kunde') }}</th>
                        <th>{{ __('Ort') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Tour') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->scheduled_for?->format('d.m.Y') }}</td>
                            <td>
                                <a href="{{ route('service-orders.show', $order) }}" class="link">{{ $order->title }}</a>
                                @if ($order->priority !== 'normal')
                                    <span class="badge badge-warning badge-xs ml-1">{{ __($order->priority) }}</span>
                                @endif
                            </td>
                            <td>{{ $order->customer?->name }}</td>
                            <td>{{ $order->address_city }}</td>
                            <td><span class="badge badge-ghost badge-sm">{{ __($order->status) }}</span></td>
                            <td>
                                @if ($order->tour_id)
                                    <a href="{{ route('tours.show', $order->tour_id) }}" class="link link-hover">#{{ $order->tour_id }}</a>
                                    @if ($order->tour_position) <span class="text-xs text-base-content/60">#{{ $order->tour_position }}</span> @endif
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('service-orders.edit', $order) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('service-orders.destroy', $order) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Auftrag wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state :compact="true" :title="__('Keine Aufträge im gewählten Zeitraum')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </x-card>

        {{ $orders->links() }}
    </x-page-shell>
@endsection

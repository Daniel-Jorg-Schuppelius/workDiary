@extends('layouts.app')

@section('title', __('Aufträge'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Service-Aufträge') }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('service-orders.create') }}" class="btn btn-sm btn-primary">
                    <x-icon name="add" /> {{ __('Neuer Auftrag') }}
                </a>
            </div>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2 rounded-box border border-base-300 bg-base-100 p-3">
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

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
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
                                <a href="{{ route('service-orders.edit', $order) }}" class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('service-orders.destroy', $order) }}" class="inline"
                                      onsubmit="return confirm('{{ __('Auftrag wirklich löschen?') }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-base-content/60">{{ __('Keine Aufträge im gewählten Zeitraum.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    </div>
@endsection

{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Portal-Terminbuchung (Feature 087): anonyme Slots, zweiphasige Anfrage.
--}}
@extends('customer.layout')

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Termin anfragen') }}</h1>
    <p class="mb-4 text-sm text-base-content/70">{{ __('Sie wählen ein Zeitfenster, wir bestätigen verbindlich — erst dann ist der Termin fest.') }}</p>

    @if (session('error'))
        <div class="alert alert-error mb-4 text-sm">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="mb-6 rounded-box bg-base-100 p-4 shadow">
        <form method="GET" action="{{ route('customer.appointments.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="form-control">
                <span class="label-text">{{ __('Leistung') }}</span>
                <select name="service" class="select select-bordered select-sm w-64">
                    <option value="">{{ __('— bitte wählen —') }}</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->sqid }}" @selected($selected?->id === $service->id)>
                            {{ $service->title }} ({{ $service->duration_minutes }} min)
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Tag') }}</span>
                <input type="date" name="day" value="{{ $day?->format('Y-m-d') }}" class="input input-bordered input-sm">
            </label>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Fenster anzeigen') }}</button>
        </form>

        @if ($selected !== null)
            @if ($selected->description)
                <p class="mt-2 text-sm text-base-content/70">{{ $selected->description }}</p>
            @endif
            <div class="mt-4">
                @if ($windows === [])
                    <p class="text-sm text-base-content/60">{{ __('An diesem Tag sind keine Fenster frei — bitte einen anderen Tag wählen.') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($windows as $window)
                            <form method="POST" action="{{ route('customer.appointments.store') }}">
                                @csrf
                                <input type="hidden" name="service" value="{{ $selected->sqid }}">
                                <input type="hidden" name="start" value="{{ $window['start']->toIso8601String() }}">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    {{ $window['start']->format('H:i') }}–{{ $window['end']->format('H:i') }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    <h2 class="mb-2 font-semibold">{{ __('Meine Anfragen') }}</h2>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Termin') }}</x-table.th>
                <x-table.th>{{ __('Leistung') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $request)
            <tr>
                <td class="whitespace-nowrap">{{ $request->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                <td>{{ $request->service_label ?? '—' }}</td>
                <td>
                    @php($statusMap = [
                        'requested' => ['badge-info', __('angefragt')],
                        'confirmed' => ['badge-success', __('bestätigt')],
                        'declined' => ['badge-error', __('abgelehnt')],
                        'canceled' => ['badge-ghost', __('storniert')],
                        'superseded' => ['badge-ghost', __('ersetzt')],
                    ])
                    @php([$tone, $label] = $statusMap[$request->status] ?? ['badge-ghost', $request->status])
                    <span class="badge {{ $tone }} badge-sm">{{ $label }}</span>
                    @if ($request->status === 'declined' && $request->decline_reason)
                        <span class="block text-xs text-base-content/60">{{ $request->decline_reason }}</span>
                    @endif
                </td>
                <td class="text-right">
                    @if (in_array($request->status, ['requested', 'confirmed'], true))
                        <form method="POST" action="{{ route('customer.appointments.cancel', $request) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-xs">{{ __('Stornieren') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Noch keine Terminanfragen.')" />
        @endforelse
    </x-table>
@endsection

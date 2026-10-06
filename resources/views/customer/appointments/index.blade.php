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
                <span class="label-text">{{ __('Datum') }}</span>
                <input type="date" name="day" value="{{ $day?->format('Y-m-d') }}" class="input input-bordered input-sm">
            </label>
            <x-button type="submit">{{ __('Fenster anzeigen') }}</x-button>
        </form>

        @if ($selected !== null)
            @if ($selected->description)
                <p class="mt-2 text-sm text-base-content/70">{{ $selected->description }}</p>
            @endif
            <div class="mt-4">
                @if ($windows === [])
                    <p class="text-sm text-muted">{{ __('An diesem Tag sind keine Fenster frei — bitte einen anderen Tag wählen.') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($windows as $window)
                            <form method="POST" action="{{ route('customer.appointments.store') }}">
                                @csrf
                                <input type="hidden" name="service" value="{{ $selected->sqid }}">
                                <input type="hidden" name="start" value="{{ $window['start']->toIso8601String() }}">
                                <x-button type="submit" tone="outline">
                                    {{ $window['start']->format('H:i') }}–{{ $window['end']->format('H:i') }}
                                </x-button>
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
                <td class="whitespace-nowrap">{{ $request->start_at?->fdatetime() ?? '—' }}</td>
                <td>{{ $request->service_label ?? '—' }}</td>
                <td>
                    <x-status-badge :tone="$request->status->tone()" size="sm">{{ $request->status->label() }}</x-status-badge>
                    @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Declined && $request->decline_reason)
                        <span class="block text-xs text-muted">{{ $request->decline_reason }}</span>
                    @endif
                </td>
                <td class="text-right">
                    @if (in_array($request->status, [\App\Enums\Calendar\AppointmentRequestStatus::Requested, \App\Enums\Calendar\AppointmentRequestStatus::Confirmed], true))
                        <form method="POST" action="{{ route('customer.appointments.cancel', $request) }}">
                            @csrf
                            <x-button type="submit" tone="ghost" size="xs">{{ __('Stornieren') }}</x-button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Noch keine Terminanfragen.')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$requests" standing />
@endsection

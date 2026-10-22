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
    <x-page-toolbar :title="__('Termin anfragen')" :subtitle="__('Sie wählen ein Zeitfenster, wir bestätigen verbindlich — erst dann ist der Termin fest.')" class="mb-4" />

    <x-card class="mb-6">
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
            <p class="mt-2 text-sm text-base-content/70">{{ __('appointment.portal.cancel_policy', ['hours' => $selected->cancel_hours]) }}</p>
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
    </x-card>

    <x-card :title="__('Meine Anfragen')" icon="event" padding="p-0">
    <x-table bare>
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
                    @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Proposed && $request->proposed_start_at)
                        <span class="block text-xs">{{ __('appointment.portal.proposal', ['date' => $request->proposed_start_at->fdatetime()]) }}</span>
                        @if ($request->proposal_note)
                            <span class="block text-xs text-muted">{{ $request->proposal_note }}</span>
                        @endif
                    @endif
                </td>
                <td class="text-right">
                    @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Proposed)
                        <div class="inline-flex flex-wrap justify-end gap-1">
                            <form method="POST" action="{{ route('customer.appointments.proposal.accept', $request) }}">
                                @csrf
                                <x-button type="submit" size="xs">{{ __('appointment.portal.proposal_accept') }}</x-button>
                            </form>
                            <form method="POST" action="{{ route('customer.appointments.proposal.decline', $request) }}">
                                @csrf
                                <x-button type="submit" tone="ghost" size="xs">{{ __('appointment.portal.proposal_decline') }}</x-button>
                            </form>
                        </div>
                    @endif
                    @if (in_array($request->status, [\App\Enums\Calendar\AppointmentRequestStatus::Requested, \App\Enums\Calendar\AppointmentRequestStatus::Confirmed], true))
                        @if ($request->withinCancelDeadline())
                            <form method="POST" action="{{ route('customer.appointments.cancel', $request) }}" class="inline-flex flex-col items-end">
                                @csrf
                                <x-button type="submit" tone="ghost" size="xs">{{ __('Stornieren') }}</x-button>
                                @if ($request->cancelDeadline())
                                    <span class="text-xs text-muted">{{ __('appointment.portal.cancel_until', ['date' => $request->cancelDeadline()->fdatetime()]) }}</span>
                                @endif
                            </form>
                        @else
                            <span class="text-xs text-muted">{{ __('appointment.portal.cancel_expired') }}</span>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="event" :title="__('Noch keine Terminanfragen.')" compact />
        @endforelse
    </x-table>
    </x-card>

    <x-pagination :paginator="$requests" standing />
@endsection

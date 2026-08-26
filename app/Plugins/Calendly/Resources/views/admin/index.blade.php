{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Calendly'))
@section('nav-title', __('Calendly'))

@section('content')
<x-index-page
    :subtitle="__('Empfängt extern über Calendly gebuchte Termine als bestätigungspflichtige Terminwünsche und erzeugt Einmal-Buchungslinks.')"
    :badge="$connection && $connection->isActive() ? __('verbunden') : __('nicht verbunden')"
    :badge-tone="$connection && $connection->isActive() ? 'success' : 'ghost'">

    <x-slot:actions>
        @if ($connection && $connection->isActive())
            @unless ($subscription)
                <form method="POST" action="{{ route('admin.calendly.subscribe') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Webhook anmelden') }}</button>
                </form>
            @else
                <span class="badge badge-success badge-sm self-center">{{ __('Webhook aktiv') }}</span>
            @endunless

            <form method="POST" action="{{ route('admin.calendly.backfill') }}">
                @csrf
                <button type="submit" class="btn btn-sm">{{ __('Jetzt abgleichen') }}</button>
            </form>

            <x-action-form :action="route('admin.calendly.disconnect')" :confirm="__('Verbindung wirklich trennen?')">
                <button type="submit" class="btn btn-sm btn-ghost">{{ __('Trennen') }}</button>
            </x-action-form>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.calendly.oauth.start') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Mit Calendly verbinden') }}</button>
            </form>
        @endif
    </x-slot:actions>

    @if (session('success'))
        <div class="alert alert-success text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif

    @unless ($configured)
        <div class="alert alert-warning text-sm">
            {{ __('Calendly Client-ID/Secret sind nicht konfiguriert (CALENDLY_CLIENT_ID / CALENDLY_CLIENT_SECRET).') }}
        </div>
    @endunless

    {{-- Einmal-Buchungslink (Outbound, P5) --}}
    @if ($connection && $connection->isActive())
        @if (session('calendly_booking_url'))
            <div class="alert alert-info text-sm">
                <span>{{ __('Buchungslink') }}:
                    <a href="{{ session('calendly_booking_url') }}" target="_blank" rel="noopener"
                       class="link break-all">{{ session('calendly_booking_url') }}</a>
                </span>
            </div>
        @endif

        <div class="rounded-box border border-base-300 p-4 space-y-2">
            <h3 class="font-medium">{{ __('Einmal-Buchungslink') }}</h3>
            <p class="text-xs text-muted">
                {{ __('Erzeugt einen einmaligen Calendly-Buchungslink je Lead/Leistung — nicht öffentlich gelistet, zum direkten Teilen.') }}
            </p>
            <form method="POST" action="{{ route('admin.calendly.booking-link') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="booking-link-name">{{ __('Bezeichnung') }}</label>
                    <input id="booking-link-name" name="name" type="text" required maxlength="255"
                           class="input input-sm input-bordered" placeholder="{{ __('z. B. Erstberatung') }}" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="booking-link-duration">{{ __('Dauer (Minuten)') }}</label>
                    <input id="booking-link-duration" name="duration" type="number" required min="5" max="480" value="30"
                           class="input input-sm input-bordered w-28" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="booking-link-days">{{ __('Buchbar (Tage)') }}</label>
                    <input id="booking-link-days" name="days" type="number" min="1" max="90" value="30"
                           class="input input-sm input-bordered w-24" />
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Link erzeugen') }}</button>
            </form>
        </div>
    @endif

    {{-- Offene Terminwünsche (zweiphasige Bestätigung) --}}
    @if ($requests->isEmpty())
        <x-empty-state framed icon="event_busy"
            :title="__('Keine offenen Terminwünsche')"
            :message="__('Es liegen aktuell keine bestätigungspflichtigen Terminwünsche vor.')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Termin') }}</th>
                    <th>{{ __('Invitee') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($requests as $request)
                <tr>
                    <td class="whitespace-nowrap">
                        {{ optional($request->start_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}
                        <div class="text-xs text-muted">{{ $request->service_label }}</div>
                    </td>
                    <td>
                        {{ $request->invitee_name }}
                        <div class="text-xs text-muted">{{ $request->invitee_email }}</div>
                    </td>
                    <td>
                        @if ($request->customer_id)
                            {{ optional($request->customer)->name }}
                        @else
                            <span class="badge badge-warning badge-sm">{{ __('nicht zugeordnet') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <form method="POST" action="{{ route('admin.calendly.requests.confirm', $request) }}">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-primary">{{ __('Bestätigen') }}</button>
                            </form>
                            <button type="button" class="btn btn-xs btn-ghost"
                                    data-open-dialog="decline-dialog-{{ $request->getKey() }}">{{ __('Ablehnen') }}</button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{-- Decline-Dialoge (außerhalb der Tabelle) --}}
        @foreach ($requests as $request)
            <x-modal id="decline-dialog-{{ $request->getKey() }}" :embedded="false"
                     tone="error" icon="cancel"
                     :eyebrow="__('Terminwunsch')" :title="__('Terminwunsch ablehnen')"
                     :action="route('admin.calendly.requests.decline', $request)"
                     :submit-label="__('Ablehnen')" submit-class="btn-error">
                <x-form-group :legend="__('Ablehnung')" icon="cancel" tone="error">
                    <div class="fieldset">
                        <label class="fieldset-label" for="decline-reason-{{ $request->getKey() }}">{{ __('Grund') }}</label>
                        <textarea id="decline-reason-{{ $request->getKey() }}" name="reason"
                                  rows="3" maxlength="500"
                                  class="textarea textarea-sm textarea-bordered w-full"
                                  placeholder="{{ __('Optional: Begründung für die Ablehnung') }}"></textarea>
                    </div>
                </x-form-group>
            </x-modal>
        @endforeach
    @endif
</x-index-page>
@endsection

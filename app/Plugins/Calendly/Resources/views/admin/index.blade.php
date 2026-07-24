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
                        <div class="text-xs text-base-content/50">{{ $request->service_label }}</div>
                    </td>
                    <td>
                        {{ $request->invitee_name }}
                        <div class="text-xs text-base-content/50">{{ $request->invitee_email }}</div>
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

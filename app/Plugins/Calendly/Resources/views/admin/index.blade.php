@extends('layouts.app')
@section('title', __('Calendly'))
@section('nav-title', __('Calendly'))

@section('content')
<x-index-page :subtitle="__('Empfängt extern über Calendly gebuchte Termine als bestätigungspflichtige Terminwünsche.')">
    <x-slot:actions>
        @if ($connection && $connection->isActive())
            <form method="POST" action="{{ route('admin.calendly.backfill') }}">
                @csrf
                <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('Jetzt abgleichen') }}</x-icon-btn>
            </form>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.calendly.oauth.start') }}">
                @csrf
                <x-icon-btn icon="link" tone="primary" size="sm" type="submit" show-label>{{ __('Mit Calendly verbinden') }}</x-icon-btn>
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

    {{-- Status + Verbindung --}}
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                @if ($connection && $connection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('verbunden') }}</span>
                @else
                    <span class="badge badge-ghost badge-sm">{{ __('nicht verbunden') }}</span>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($connection && $connection->isActive())
                    @unless ($subscription)
                        <form method="POST" action="{{ route('admin.calendly.subscribe') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Webhook anmelden') }}</button>
                        </form>
                    @else
                        <span class="badge badge-success badge-sm self-center">{{ __('Webhook aktiv') }}</span>
                    @endunless

                    <x-action-form :action="route('admin.calendly.disconnect')" :confirm="__('Verbindung wirklich trennen?')">
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('Trennen') }}</button>
                    </x-action-form>
                @endif
            </div>
        </div>

        @unless ($configured)
            <div class="alert alert-warning text-sm">
                {{ __('Calendly Client-ID/Secret sind nicht konfiguriert (CALENDLY_CLIENT_ID / CALENDLY_CLIENT_SECRET).') }}
            </div>
        @endunless
    </div>

    {{-- Offene Terminwünsche (zweiphasige Bestätigung) --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="border-b border-base-300 px-4 py-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Offene Terminwünsche') }}</h2>
        </div>

        @if ($requests->isEmpty())
            <x-empty-state framed icon="event_available" :title="__('Keine offenen Terminwünsche.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Termin') }}</th>
                            <th>{{ __('Invitee') }}</th>
                            <th>{{ __('Kunde') }}</th>
                            <th class="text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-index-page>
@endsection

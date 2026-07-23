@extends('layouts.app')
@section('title', __('Calendly'))
@section('nav-title', __('Calendly'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
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
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Calendly-Terminbuchung') }}</h1>
                @if ($connection && $connection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('verbunden') }}</span>
                @else
                    <span class="badge badge-ghost badge-sm">{{ __('nicht verbunden') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Empfängt extern über Calendly gebuchte Termine als bestätigungspflichtige Terminwünsche.') }}
            </p>

            @unless ($configured)
                <div class="alert alert-warning text-sm">
                    {{ __('Calendly Client-ID/Secret sind nicht konfiguriert (CALENDLY_CLIENT_ID / CALENDLY_CLIENT_SECRET).') }}
                </div>
            @endunless

            <div class="mt-3 flex flex-wrap gap-2">
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
            </div>
        </div>

        {{-- Offene Terminwünsche (zweiphasige Bestätigung) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-3 font-['Space_Grotesk'] text-base font-semibold">{{ __('Offene Terminwünsche') }}</h2>

            @if ($requests->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine offenen Terminwünsche.') }}</p>
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
                                            <form method="POST" action="{{ route('admin.calendly.requests.decline', $request) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-ghost">{{ __('Ablehnen') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection

{{--
  2FA-Selbstverwaltung: aktivieren (QR + Bestätigung), Recovery-Codes, deaktivieren.
  Design nach App-Standard (x-index-page + Standard-Cards).
--}}
@extends('layouts.app')

@section('title', __('Zwei-Faktor-Authentifizierung'))
@section('nav-title', __('Zwei-Faktor-Authentifizierung'))

@php
    $statusBadge = $enabled ? __('Aktiv') : ($pending ? __('Einrichtung offen') : __('Inaktiv'));
    $statusTone = $enabled ? 'success' : ($pending ? 'warning' : 'neutral');
@endphp

@section('content')
<x-index-page
    :subtitle="__('Zusätzlicher Schutz mit Einmalcode aus einer Authenticator-App')"
    :badge="$statusBadge"
    :badge-tone="$statusTone"
>
    @if (session('success'))
        <div class="alert alert-success text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Recovery-Codes einmalig nach Bestätigung/Neuerzeugung --}}
    @if (! empty($recoveryCodes))
        <article class="card border border-warning/40 bg-warning/5 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Recovery-Codes') }}</h2>
                <p class="text-sm text-base-content/70">{{ __('Bewahren Sie diese Codes sicher auf. Jeder Code funktioniert einmal, falls Sie keinen Zugriff auf Ihre Authenticator-App haben.') }}</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                    @foreach ($recoveryCodes as $rc)
                        <code class="rounded bg-base-200 px-2 py-1 text-center select-all">{{ $rc }}</code>
                    @endforeach
                </div>
            </div>
        </article>
    @endif

    @if ($enabled)
        {{-- Aktiv: Recovery-Codes neu erzeugen / deaktivieren --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold text-success">
                    <x-icon name="verified_user" /> {{ __('Zwei-Faktor-Authentifizierung ist aktiv') }}
                </h2>
                <p class="text-sm text-base-content/70">{{ __('Ihr Konto ist mit einem zweiten Faktor geschützt.') }}</p>

                <div class="mt-2 grid gap-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('account.2fa.recovery') }}" class="space-y-2 rounded-box border border-base-300 bg-base-200/40 p-3">
                        @csrf
                        <label class="text-xs uppercase tracking-wider text-base-content/60" for="rc-code">{{ __('Recovery-Codes neu erzeugen') }}</label>
                        <input id="rc-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="input input-sm input-bordered w-full" placeholder="{{ __('Aktueller Code') }}" required>
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit" show-label>{{ __('Neu erzeugen') }}</x-icon-btn>
                    </form>

                    @unless (auth()->user()->organization?->two_factor_required)
                        <form method="POST" action="{{ route('account.2fa.disable') }}" class="space-y-2 rounded-box border border-error/30 bg-error/5 p-3">
                            @csrf
                            @method('DELETE')
                            <label class="text-xs uppercase tracking-wider text-base-content/60" for="dis-code">{{ __('Deaktivieren') }}</label>
                            <input id="dis-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="input input-sm input-bordered w-full" placeholder="{{ __('Aktueller Code') }}" required>
                            <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
                        </form>
                    @else
                        <div class="flex items-center rounded-box border border-base-300 bg-base-200/40 p-3 text-sm text-base-content/60">
                            {{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.') }}
                        </div>
                    @endunless
                </div>
            </div>
        </article>
    @elseif ($pending)
        {{-- Einrichtung: QR scannen + Code bestätigen --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Einrichtung abschließen') }}</h2>

                <div class="grid gap-6 md:grid-cols-[auto_1fr] md:items-start">
                    <div class="mx-auto w-fit rounded-box border border-base-300 bg-white p-3">
                        {!! $qrSvg !!}
                    </div>

                    <div class="space-y-4">
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-base-content/70">
                            <li>{{ __('QR-Code mit einer Authenticator-App scannen (z. B. Google Authenticator, Aegis, 1Password).') }}</li>
                            <li>{{ __('Den dort angezeigten 6-stelligen Code unten eingeben.') }}</li>
                        </ol>

                        <div class="rounded-box border border-base-300 bg-base-200/40 p-3 text-xs">
                            <span class="uppercase tracking-wider text-base-content/60">{{ __('Manueller Schlüssel') }}</span>
                            <code class="mt-1 block break-all font-mono select-all">{{ $secret }}</code>
                        </div>

                        <form method="POST" action="{{ route('account.2fa.confirm') }}" class="flex items-end gap-2">
                            @csrf
                            <div class="grow">
                                <label class="text-xs uppercase tracking-wider text-base-content/60" for="confirm-code">{{ __('Code aus der App') }}</label>
                                <input id="confirm-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="input input-bordered w-full tracking-[0.4em] font-mono" placeholder="000000" required autofocus>
                            </div>
                            <x-icon-btn icon="check" tone="primary" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                        </form>
                    </div>
                </div>
            </div>
        </article>
    @else
        {{-- Inaktiv: aktivieren --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Konto zusätzlich absichern') }}</h2>
                <p class="text-sm text-base-content/70">{{ __('Schützen Sie Ihr Konto mit einem zweiten Faktor (Einmalcode aus einer Authenticator-App) zusätzlich zum Passwort.') }}</p>
                <form method="POST" action="{{ route('account.2fa.enable') }}" class="mt-1">
                    @csrf
                    <x-icon-btn icon="shield" tone="primary" type="submit" show-label>{{ __('Aktivieren') }}</x-icon-btn>
                </form>
            </div>
        </article>
    @endif
</x-index-page>
@endsection

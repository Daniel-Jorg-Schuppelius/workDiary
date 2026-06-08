{{--
  2FA-Selbstverwaltung: aktivieren (QR + Bestätigung), Recovery-Codes, deaktivieren.
--}}
@extends('layouts.app')

@section('title', __('Zwei-Faktor-Authentifizierung'))
@section('nav-title', __('Zwei-Faktor-Authentifizierung'))

@section('content')
<x-page-shell>

    @if (session('success'))
        <div class="alert alert-success text-sm">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning text-sm">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Recovery-Codes einmalig nach Bestätigung/Neuerzeugung --}}
    @if (! empty($recoveryCodes))
        <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
            <p class="font-semibold">{{ __('Recovery-Codes') }}</p>
            <p class="text-sm">{{ __('Bewahren Sie diese Codes sicher auf. Jeder Code funktioniert einmal, falls Sie keinen Zugriff auf Ihre Authenticator-App haben.') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach ($recoveryCodes as $rc)
                    <code class="rounded bg-base-200 px-2 py-1">{{ $rc }}</code>
                @endforeach
            </div>
        </div>
    @endif

    @if ($enabled)
        <div class="card border border-success/30 bg-base-100 p-4">
            <p class="flex items-center gap-2 font-semibold text-success">
                <x-icon name="verified_user" /> {{ __('Zwei-Faktor-Authentifizierung ist aktiv.') }}
            </p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('account.2fa.recovery') }}" class="space-y-2">
                    @csrf
                    <label class="label" for="rc-code">{{ __('Recovery-Codes neu erzeugen') }}</label>
                    <input id="rc-code" name="code" type="text" inputmode="numeric" class="input input-bordered w-full" placeholder="{{ __('Aktueller Code') }}" required>
                    <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit" show-label>{{ __('Neu erzeugen') }}</x-icon-btn>
                </form>

                @unless (auth()->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('account.2fa.disable') }}" class="space-y-2">
                        @csrf
                        @method('DELETE')
                        <label class="label" for="dis-code">{{ __('Deaktivieren') }}</label>
                        <input id="dis-code" name="code" type="text" inputmode="numeric" class="input input-bordered w-full" placeholder="{{ __('Aktueller Code') }}" required>
                        <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
                    </form>
                @else
                    <p class="text-sm text-base-content/60">{{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.') }}</p>
                @endunless
            </div>
        </div>
    @elseif ($pending)
        <div class="card border border-base-300 bg-base-100 p-4">
            <p class="font-semibold">{{ __('Einrichtung abschließen') }}</p>
            <p class="text-sm text-base-content/70">{{ __('Scannen Sie den QR-Code mit Ihrer Authenticator-App (z. B. Google Authenticator, Aegis, 1Password) und geben Sie anschließend den angezeigten 6-stelligen Code ein.') }}</p>

            <div class="mt-4 flex flex-col items-center gap-3">
                <div class="rounded-box bg-white p-3">{!! $qrSvg !!}</div>
                <p class="text-xs text-base-content/60">{{ __('Manueller Schlüssel') }}: <code class="select-all">{{ $secret }}</code></p>
            </div>

            <form method="POST" action="{{ route('account.2fa.confirm') }}" class="mt-4 flex items-end gap-2">
                @csrf
                <div class="grow">
                    <label class="label" for="confirm-code">{{ __('Code aus der App') }}</label>
                    <input id="confirm-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="input input-bordered w-full" placeholder="000000" required autofocus>
                </div>
                <x-icon-btn icon="check" tone="primary" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
            </form>
        </div>
    @else
        <div class="card border border-base-300 bg-base-100 p-4">
            <p class="font-semibold">{{ __('Zwei-Faktor-Authentifizierung') }}</p>
            <p class="text-sm text-base-content/70">{{ __('Schützen Sie Ihr Konto mit einem zweiten Faktor (Einmalcode aus einer Authenticator-App) zusätzlich zum Passwort.') }}</p>
            <form method="POST" action="{{ route('account.2fa.enable') }}" class="mt-3">
                @csrf
                <x-icon-btn icon="shield" tone="primary" type="submit" show-label>{{ __('Aktivieren') }}</x-icon-btn>
            </form>
        </div>
    @endif

</x-page-shell>
@endsection

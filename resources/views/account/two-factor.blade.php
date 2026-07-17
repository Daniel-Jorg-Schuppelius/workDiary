{{--
  2FA-Selbstverwaltung (Mehr-Methoden): Authenticator-App (TOTP), E-Mail-Code,
  Recovery-Codes, Faktoren entfernen. Design nach App-Standard (x-index-page).
--}}
@extends('layouts.app')

@section('title', __('Zwei-Faktor-Authentifizierung'))
@section('nav-title', __('Zwei-Faktor-Authentifizierung'))

@php
    use App\Enums\Auth\TwoFactorType;
    $statusBadge = $enabled ? __('Aktiv') : (($pendingTotp || $pendingEmail) ? __('Einrichtung offen') : __('Inaktiv'));
    $statusTone = $enabled ? 'success' : (($pendingTotp || $pendingEmail) ? 'warning' : 'neutral');
    $emailActive = $credentials->firstWhere('type', TwoFactorType::Email);
@endphp

@section('content')
<x-index-page
    :subtitle="__('Zusätzlicher Schutz mit einem zweiten Faktor – Authenticator-App oder E-Mail-Code')"
    :badge="$statusBadge"
    :badge-tone="$statusTone"
>
    <x-slot:actions>
        <x-help-button topic="account.two-factor" />
    </x-slot:actions>

    @if (session('success'))<div class="alert alert-success text-sm">{{ session('success') }}</div>@endif
    <x-validation-errors first />

    {{-- Recovery-Codes einmalig --}}
    @if (! empty($recoveryCodes))
        <x-card class="border-warning/40 bg-warning/5">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Recovery-Codes') }}</h2>
            <p class="text-sm text-base-content/70">{{ __('Sicher aufbewahren – jeder Code funktioniert einmal, falls Sie keinen Zugriff auf Ihren zweiten Faktor haben.') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                @foreach ($recoveryCodes as $rc)
                    <code class="rounded bg-base-200 px-2 py-1 text-center select-all">{{ $rc }}</code>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Aktive Faktoren --}}
    @if ($enabled)
        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Aktive Faktoren') }}</h2>
            <ul class="mt-2 divide-y divide-base-200">
                @if ($hasTotp)
                    <li class="flex items-center justify-between py-2">
                        <span class="flex items-center gap-2 text-sm"><x-icon name="smartphone" /> {{ __('Authenticator-App') }}</span>
                        <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                    </li>
                @endif
                @foreach ($credentials as $cred)
                    <li class="flex items-center justify-between py-2">
                        <span class="flex items-center gap-2 text-sm"><x-icon :name="$cred->type->icon()" /> {{ $cred->type->label() }} <span class="text-base-content/50">{{ $cred->label }}</span></span>
                        <form method="POST" action="{{ route('account.2fa.credential.destroy', $cred) }}">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Entfernen')" />
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- Methode hinzufügen --}}
    <x-card>
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Methode hinzufügen') }}</h2>
        <div class="mt-2 grid gap-4 md:grid-cols-2">

            {{-- Authenticator-App --}}
            <div class="rounded-box border border-base-300 p-4 space-y-3">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="smartphone" /> {{ __('Authenticator-App') }}</p>
                @if ($hasTotp)
                    <p class="text-sm text-success">{{ __('Aktiv.') }}</p>
                @elseif ($pendingTotp)
                    <div class="flex flex-col items-center gap-2">
                        <div class="rounded-box border border-base-300 bg-white p-3">{!! $qrSvg !!}</div>
                        <p class="text-xs text-base-content/60">{{ __('Manueller Schlüssel') }}: <code class="select-all">{{ $secret }}</code></p>
                    </div>
                    <form method="POST" action="{{ route('account.2fa.confirm') }}" class="flex items-end gap-2">
                        @csrf
                        <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                               class="input input-sm input-bordered w-full text-center tracking-[0.3em] font-mono" placeholder="000000">
                        <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                    </form>
                @else
                    <p class="text-sm text-base-content/70">{{ __('Einmalcode aus einer App (Google Authenticator, Aegis, 1Password).') }}</p>
                    <form method="POST" action="{{ route('account.2fa.enable') }}">
                        @csrf
                        <x-icon-btn icon="qr_code_2" tone="primary" size="sm" type="submit" show-label>{{ __('QR-Code anzeigen') }}</x-icon-btn>
                    </form>
                @endif
            </div>

            {{-- E-Mail-Code --}}
            <div class="rounded-box border border-base-300 p-4 space-y-3">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="mail" /> {{ __('E-Mail-Code') }}</p>
                @if ($emailActive)
                    <p class="text-sm text-success">{{ __('Aktiv.') }}</p>
                @elseif ($pendingEmail)
                    <p class="text-sm text-base-content/70">{{ __('Wir haben einen Code an Ihre E-Mail gesendet.') }}</p>
                    <form method="POST" action="{{ route('account.2fa.email.confirm') }}" class="flex items-end gap-2">
                        @csrf
                        <input name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                               class="input input-sm input-bordered w-full text-center tracking-[0.3em] font-mono" placeholder="000000">
                        <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                    </form>
                    <form method="POST" action="{{ route('account.2fa.email.resend') }}">
                        @csrf
                        <x-icon-btn icon="refresh" tone="ghost" size="sm" type="submit" show-label>{{ __('Code erneut senden') }}</x-icon-btn>
                    </form>
                @else
                    <p class="text-sm text-base-content/70">{{ __('Einmalcode an Ihre hinterlegte Adresse:') }} <span class="font-mono">{{ auth()->user()->email }}</span></p>
                    <form method="POST" action="{{ route('account.2fa.email.enable') }}">
                        @csrf
                        <x-icon-btn icon="mail" tone="primary" size="sm" type="submit" show-label>{{ __('E-Mail-Code aktivieren') }}</x-icon-btn>
                    </form>
                @endif
            </div>

            {{-- Sicherheitsschlüssel / Passkey (FIDO2) --}}
            <div class="rounded-box border border-base-300 p-4 space-y-3 md:col-span-2" data-webauthn-block>
                <p class="flex items-center gap-2 font-semibold"><x-icon name="key" /> {{ __('Sicherheitsschlüssel / Passkey (FIDO2)') }}</p>
                <p class="text-sm text-base-content/70">{{ __('Phishing-resistente Anmeldung mit Passkey, Smartphone oder Hardware-Schlüssel.') }}</p>
                <p id="passkey-error" class="hidden text-sm text-error"></p>
                <x-icon-btn icon="key" tone="primary" size="sm" show-label
                            data-webauthn-register
                            data-options="{{ route('account.2fa.webauthn.options') }}"
                            data-target="{{ route('account.2fa.webauthn.register') }}"
                            data-error="passkey-error">{{ __('Passkey hinzufügen') }}</x-icon-btn>
            </div>
        </div>
    </x-card>

    {{-- Recovery + Deaktivieren --}}
    @if ($enabled)
        <x-card>
            <div class="grid gap-4 md:grid-cols-2">
                @if ($hasTotp)
                    <form method="POST" action="{{ route('account.2fa.recovery') }}" class="space-y-2 rounded-box border border-base-300 bg-base-200/40 p-3">
                        @csrf
                        <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Recovery-Codes neu erzeugen') }}</label>
                        <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="input input-sm input-bordered w-full" placeholder="{{ __('Aktueller App-Code') }}">
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit" show-label>{{ __('Neu erzeugen') }}</x-icon-btn>
                    </form>
                @endif
                @unless (auth()->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('account.2fa.disable') }}" class="space-y-2 rounded-box border border-error/30 bg-error/5 p-3">
                        @csrf @method('DELETE')
                        <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Alles deaktivieren') }}</label>
                        <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="input input-sm input-bordered w-full" placeholder="{{ __('App- oder Recovery-Code') }}">
                        <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
                    </form>
                @else
                    <div class="flex items-center rounded-box border border-base-300 bg-base-200/40 p-3 text-sm text-base-content/60">
                        {{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; vollständiges Deaktivieren ist nicht möglich.') }}
                    </div>
                @endunless
            </div>
        </x-card>
    @endif
</x-index-page>
@include('partials.webauthn-script')
@endsection

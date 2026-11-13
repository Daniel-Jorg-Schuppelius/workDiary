{{--
  Created on   : Mon Jun 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : two-factor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="flex flex-wrap items-center gap-2 text-sm">
                            <x-icon :name="$cred->type->icon()" /> {{ $cred->type->label() }} <span class="text-muted">{{ $cred->label }}</span>
                            {{-- Feature 205 (MVP-1656): letzte Nutzung je Anmeldemittel --}}
                            <span class="text-xs text-muted">{{ __('login_policy.passkey.last_used', ['date' => $cred->last_used_at ? \App\Support\Tz::toLocal($cred->last_used_at)?->translatedFormat('d.m.Y H:i') : '—']) }}</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2">
                            @if ($cred->type === TwoFactorType::Webauthn)
                                <form method="POST" action="{{ route('account.2fa.credential.update', $cred) }}" class="flex items-center gap-1">
                                    @csrf @method('PATCH')
                                    <input type="text" name="label" value="{{ $cred->label }}" maxlength="100" required
                                           aria-label="{{ __('login_policy.passkey.name') }}" class="input input-xs input-bordered w-40">
                                    <x-icon-btn icon="edit" size="sm" type="submit" :label="__('login_policy.passkey.rename')" />
                                </form>
                            @endif
                            {{-- Entfernen nur mit erneuter Anmeldung (Passwort bzw. App-/Wiederherstellungscode). --}}
                            <form method="POST" action="{{ route('account.2fa.credential.destroy', $cred) }}" class="flex items-center gap-1">
                                @csrf @method('DELETE')
                                @unless ($recentlyAuthenticated ?? false)
                                    @if (auth()->user()->is_new_system)
                                        <input type="password" name="current_password" autocomplete="current-password" required
                                               aria-label="{{ __('login_policy.passkey.confirm_password') }}" placeholder="{{ __('login_policy.passkey.confirm_password') }}"
                                               class="input input-xs input-bordered w-36">
                                    @else
                                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                               aria-label="{{ __('App- oder Recovery-Code') }}" placeholder="{{ __('App- oder Recovery-Code') }}"
                                               class="input input-xs input-bordered w-36">
                                    @endif
                                @endunless
                                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Entfernen')" />
                            </form>
                        </span>
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
                        <p class="text-xs text-muted">{{ __('Manueller Schlüssel') }}: <code class="select-all">{{ $secret }}</code></p>
                    </div>
                    <form method="POST" action="{{ route('account.2fa.confirm') }}" class="flex items-end gap-2">
                        @csrf
                        <input aria-label="{{ __('Bestätigungscode') }}" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
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
                        <input aria-label="{{ __('Code aus der E-Mail') }}" name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" required
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
                <p class="text-sm text-base-content/70">{{ __('login_policy.passkey.login_hint') }}</p>
                @if ($passkeyRequired ?? false)
                    {{-- Feature 205 (MVP-1656): Passkey-Pflicht der Rolle --}}
                    <p class="text-sm text-warning">{{ __('login_policy.passkey.required_hint') }}</p>
                @endif
                <p id="passkey-error" class="hidden text-sm text-error"></p>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" id="passkey-label" maxlength="100" placeholder="{{ __('login_policy.passkey.name_placeholder') }}"
                           aria-label="{{ __('login_policy.passkey.name') }}" class="input input-sm input-bordered w-56">
                    <x-icon-btn icon="key" tone="primary" size="sm" show-label
                                data-webauthn-register
                                data-label-input="passkey-label"
                                data-options="{{ route('account.2fa.webauthn.options') }}"
                                data-target="{{ route('account.2fa.webauthn.register') }}"
                                data-error="passkey-error">{{ __('Passkey hinzufügen') }}</x-icon-btn>
                </div>
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
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Recovery-Codes neu erzeugen') }}</label>
                        <input aria-label="{{ __('Aktueller App-Code') }}" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="input input-sm input-bordered w-full" placeholder="{{ __('Aktueller App-Code') }}">
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit" show-label>{{ __('Neu erzeugen') }}</x-icon-btn>
                    </form>
                @endif
                @unless (auth()->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('account.2fa.disable') }}" class="space-y-2 rounded-box border border-error/30 bg-error/5 p-3">
                        @csrf @method('DELETE')
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Alles deaktivieren') }}</label>
                        <input aria-label="{{ __('App- oder Recovery-Code') }}" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="input input-sm input-bordered w-full" placeholder="{{ __('App- oder Recovery-Code') }}">
                        <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
                    </form>
                @else
                    <div class="flex items-center rounded-box border border-base-300 bg-base-200/40 p-3 text-sm text-muted">
                        {{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; vollständiges Deaktivieren ist nicht möglich.') }}
                    </div>
                @endunless
            </div>
        </x-card>
    @endif

    {{-- MVP-1008: fremde Anmeldung — Konto sichern statt nur Passwort ändern. --}}
    <x-card class="border-error/30">
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.account_secure.self_heading') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('security.account_secure.self_hint') }}</p>
        <x-action-form :action="route('account.secure')" class="mt-3"
              :confirm="__('security.account_secure.confirm_self')"
              confirm-icon="shield_lock"
              confirm-tone="error"
              :confirm-label="__('security.account_secure.action_self')">
            <x-icon-btn icon="shield_lock" tone="error" size="sm" type="submit" show-label>{{ __('security.account_secure.action_self') }}</x-icon-btn>
        </x-action-form>
    </x-card>
</x-index-page>
@include('partials.webauthn-script')
@endsection

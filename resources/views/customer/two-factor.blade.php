{{--
  Created on   : Mon Jun 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : two-factor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@php
    use App\Enums\Auth\TwoFactorType;
    $statusTone = $enabled ? 'success' : (($pendingTotp || $pendingEmail) ? 'warning' : 'ghost');
    $statusBadge = $enabled ? __('Aktiv') : (($pendingTotp || $pendingEmail) ? __('Einrichtung offen') : __('Inaktiv'));
    $emailActive = $credentials->firstWhere('type', TwoFactorType::Email);
@endphp

@section('content')
    <div class="max-w-2xl mx-auto mt-8 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-icon name="verified_user" />
                {{ __('Zwei-Faktor-Authentifizierung') }}
            </h1>
            <x-status-badge :tone="$statusTone" size="sm">{{ $statusBadge }}</x-status-badge>
        </div>

        <x-validation-errors first />

        {{-- Recovery-Codes einmalig --}}
        @if (! empty($recoveryCodes))
            <div class="border border-warning/40 bg-warning/10 rounded p-4">
                <p class="font-semibold">{{ __('Recovery-Codes') }}</p>
                <p class="text-sm text-base-content/70">{{ __('Sicher aufbewahren – jeder Code funktioniert einmal.') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                    @foreach ($recoveryCodes as $rc)<code class="bg-base-200 rounded px-2 py-1 text-center select-all">{{ $rc }}</code>@endforeach
                </div>
            </div>
        @endif

        {{-- Aktive Faktoren --}}
        @if ($enabled)
            <div class="border border-base-300 bg-base-100 rounded p-4">
                <p class="font-semibold">{{ __('Aktive Faktoren') }}</p>
                <ul class="mt-2 divide-y divide-base-200">
                    @if ($hasTotp)
                        <li class="flex items-center justify-between py-2 text-sm"><span class="flex items-center gap-2"><x-icon name="smartphone" /> {{ __('Authenticator-App') }}</span><x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge></li>
                    @endif
                    @foreach ($credentials as $cred)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="flex items-center gap-2"><x-icon name="{{ $cred->type->icon() }}" /> {{ $cred->type->label() }} <span class="text-muted">{{ $cred->label }}</span></span>
                            <form method="POST" action="{{ route('customer.2fa.credential.destroy', $cred) }}">@csrf @method('DELETE')<x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Entfernen')" /></form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Methode hinzufügen --}}
        <div class="border border-base-300 bg-base-100 rounded p-4">
            <p class="font-semibold">{{ __('Methode hinzufügen') }}</p>
            <div class="mt-2 grid gap-4 md:grid-cols-2">
                {{-- Authenticator-App --}}
                <div class="border border-base-300 rounded p-4 space-y-3">
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="smartphone" /> {{ __('Authenticator-App') }}</p>
                    @if ($hasTotp)
                        <p class="text-sm text-success">{{ __('Aktiv.') }}</p>
                    @elseif ($pendingTotp)
                        <div class="flex flex-col items-center gap-2">
                            <div class="border border-base-300 bg-white p-3 rounded">{!! $qrSvg !!}</div>
                            <p class="text-xs text-muted">{{ __('Schlüssel') }}: <code class="select-all">{{ $secret }}</code></p>
                        </div>
                        <form method="POST" action="{{ route('customer.2fa.confirm') }}" class="flex items-end gap-2">
                            @csrf
                            <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.3em] font-mono" placeholder="000000">
                            <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                        </form>
                    @else
                        <p class="text-sm text-base-content/70">{{ __('Einmalcode aus einer App (Google Authenticator, Aegis, 1Password).') }}</p>
                        <form method="POST" action="{{ route('customer.2fa.enable') }}">@csrf<x-icon-btn icon="qr_code_2" tone="primary" size="sm" type="submit" show-label>{{ __('QR-Code anzeigen') }}</x-icon-btn></form>
                    @endif
                </div>

                {{-- E-Mail-Code --}}
                <div class="border border-base-300 rounded p-4 space-y-3">
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="mail" /> {{ __('E-Mail-Code') }}</p>
                    @if ($emailActive)
                        <p class="text-sm text-success">{{ __('Aktiv.') }}</p>
                    @elseif ($pendingEmail)
                        <p class="text-sm text-base-content/70">{{ __('Wir haben einen Code an Ihre E-Mail gesendet.') }}</p>
                        <form method="POST" action="{{ route('customer.2fa.email.confirm') }}" class="flex items-end gap-2">
                            @csrf
                            <input name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.3em] font-mono" placeholder="000000">
                            <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                        </form>
                        <form method="POST" action="{{ route('customer.2fa.email.resend') }}">@csrf<x-icon-btn icon="refresh" tone="ghost" size="sm" type="submit" show-label>{{ __('Code erneut senden') }}</x-icon-btn></form>
                    @else
                        <p class="text-sm text-base-content/70">{{ __('Einmalcode an Ihre Adresse:') }} <span class="font-mono">{{ auth('customer')->user()->email }}</span></p>
                        <form method="POST" action="{{ route('customer.2fa.email.enable') }}">@csrf<x-icon-btn icon="mail" tone="primary" size="sm" type="submit" show-label>{{ __('E-Mail-Code aktivieren') }}</x-icon-btn></form>
                    @endif
                </div>

                {{-- Sicherheitsschlüssel / Passkey (FIDO2) --}}
                <div class="border border-base-300 rounded p-4 space-y-3 md:col-span-2" data-webauthn-block>
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="key" /> {{ __('Sicherheitsschlüssel / Passkey (FIDO2)') }}</p>
                    <p class="text-sm text-base-content/70">{{ __('Phishing-resistente Anmeldung mit Passkey, Smartphone oder Hardware-Schlüssel.') }}</p>
                    <p id="passkey-error" class="hidden text-sm text-error"></p>
                    <x-icon-btn icon="key" tone="primary" size="sm" show-label
                                data-webauthn-register
                                data-options="{{ route('customer.2fa.webauthn.options') }}"
                                data-target="{{ route('customer.2fa.webauthn.register') }}"
                                data-error="passkey-error">{{ __('Passkey hinzufügen') }}</x-icon-btn>
                </div>
            </div>
        </div>

        {{-- Recovery + Deaktivieren --}}
        @if ($enabled)
            <div class="border border-base-300 bg-base-100 rounded p-4 grid gap-4 md:grid-cols-2">
                @if ($hasTotp)
                    <form method="POST" action="{{ route('customer.2fa.recovery') }}" class="space-y-2 border border-base-300 bg-base-200/40 rounded p-3">
                        @csrf
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Recovery-Codes neu erzeugen') }}</label>
                        <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="w-full border border-base-300 rounded px-3 py-2 bg-base-100" placeholder="{{ __('Aktueller App-Code') }}">
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit" show-label>{{ __('Neu erzeugen') }}</x-icon-btn>
                    </form>
                @endif
                @unless (auth('customer')->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('customer.2fa.disable') }}" class="space-y-2 border border-error/30 bg-error/5 rounded p-3">
                        @csrf @method('DELETE')
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Alles deaktivieren') }}</label>
                        <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="w-full border border-base-300 rounded px-3 py-2 bg-base-100" placeholder="{{ __('App- oder Recovery-Code') }}">
                        <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
                    </form>
                @else
                    <div class="flex items-center border border-base-300 bg-base-200/40 rounded p-3 text-sm text-muted">{{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.') }}</div>
                @endunless
            </div>
        @endif
    </div>
    @include('partials.webauthn-script')
@endsection

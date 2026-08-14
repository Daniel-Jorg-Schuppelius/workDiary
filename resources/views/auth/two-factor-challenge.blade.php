{{--
  Created on   : Mon Jun 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : two-factor-challenge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51);
     header-action bewusst leer — mitten im Login-Flow wäre ein
     „Anmelden"-Button irreführend, Abbrechen läuft über das Logout-Form. --}}
@extends('layouts.guest')

@section('title', __('Bestätigung'))
@section('headline', __('Zwei-Faktor-Bestätigung'))

@section('header-action')
@endsection

@section('wrapper-attrs', 'x-data="twoFactorChallenge"')

@section('intro')
    <p x-show="authMode">{{ __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.') }}</p>
    <p x-show="recovery" x-cloak>{{ __('Geben Sie einen Ihrer Recovery-Codes ein.') }}</p>
@endsection

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <x-validation-errors first class="mb-4" />
        @if (session('success'))
            <div class="mb-4 alert alert-success text-sm">{{ session('success') }}</div>
        @endif

        @if ($hasWebauthn ?? false)
            <div class="mb-5" data-webauthn-block>
                <p id="passkey-error" class="mb-2 hidden text-sm text-error"></p>
                <x-button type="button" tone="primary" class="w-full gap-2 rounded-2xl font-['Space_Grotesk'] font-semibold"
                        data-webauthn-assert
                        data-options="{{ route('two-factor.login.webauthn.options') }}"
                        data-target="{{ route('two-factor.login.webauthn') }}"
                        data-error="passkey-error" icon="key">{{ __('Mit Passkey / Sicherheitsschlüssel') }}</x-button>
                <div class="my-4 flex items-center gap-3 text-xs text-base-content/50"><span class="h-px flex-1 bg-base-300"></span>{{ __('oder Code eingeben') }}<span class="h-px flex-1 bg-base-300"></span></div>
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.login.attempt') }}" class="space-y-5">
            @csrf
            <div x-show="authMode">
                <label for="code" class="mb-2 block text-sm font-medium text-base-content">{{ __('Code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                       placeholder="000000"
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-center text-2xl tracking-[0.5em] text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
            </div>
            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="mb-2 block text-sm font-medium text-base-content">{{ __('Recovery-Code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
            </div>

            <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                {{ __('Bestätigen') }}
            </x-button>
        </form>
        <button type="button" class="mt-4 w-full text-center text-sm text-primary transition hover:opacity-80"
                x-on:click="toggle()">
            <span x-show="authMode">{{ __('Stattdessen Recovery-Code verwenden') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Zurück zum Authenticator-Code') }}</span>
        </button>

        @if ($hasEmail ?? false)
            <div class="mt-6 border-t border-base-300 pt-5">
                <p class="mb-3 text-sm text-base-content/70">{{ __('Oder Code per E-Mail erhalten:') }}</p>
                <form method="POST" action="{{ route('two-factor.login.email') }}" class="mb-3">
                    @csrf
                    <x-button type="submit" tone="outline" size="sm" class="w-full rounded-2xl">{{ __('Code per E-Mail senden') }}</x-button>
                </form>
                <form method="POST" action="{{ route('two-factor.login.attempt') }}" class="space-y-3">
                    @csrf
                    <input name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('E-Mail-Code') }}"
                           class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-center text-xl tracking-[0.4em] text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <x-button type="submit" tone="primary" size="sm" class="w-full rounded-2xl">{{ __('Mit E-Mail-Code bestätigen') }}</x-button>
                </form>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-base-content/70 transition hover:opacity-80">← {{ __('Abbrechen') }}</button>
    </form>
@endsection

@section('after-body')
    @include('partials.webauthn-script')
@endsection

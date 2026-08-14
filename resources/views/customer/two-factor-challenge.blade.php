{{--
  Created on   : Mon Jun 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : two-factor-challenge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    <div class="max-w-md mx-auto bg-base-100 border border-base-300 rounded p-6 mt-10" x-data="twoFactorChallenge">
        <h1 class="text-xl font-semibold mb-2 flex items-center gap-2">
            <span class="material-symbols-outlined">verified_user</span>
            {{ __('Zwei-Faktor-Bestätigung') }}
        </h1>
        <p class="text-sm text-base-content/70 mb-4" x-show="authMode">{{ __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.') }}</p>
        <p class="text-sm text-base-content/70 mb-4" x-show="recovery" x-cloak>{{ __('Geben Sie einen Ihrer Recovery-Codes ein.') }}</p>

        <x-validation-errors first class="mb-4" />
        @if (session('success'))
            <div class="alert alert-success text-sm mb-4">{{ session('success') }}</div>
        @endif

        @if ($hasWebauthn ?? false)
            <div class="mb-5" data-webauthn-block>
                <p id="passkey-error" class="mb-2 hidden text-sm text-error"></p>
                <x-button type="button" tone="primary" class="w-full gap-2"
                        data-webauthn-assert
                        data-options="{{ route('customer.two-factor.login.webauthn.options') }}"
                        data-target="{{ route('customer.two-factor.login.webauthn') }}"
                        data-error="passkey-error" icon="key">{{ __('Mit Passkey / Sicherheitsschlüssel') }}</x-button>
                <div class="my-4 flex items-center gap-3 text-xs text-base-content/50"><span class="h-px flex-1 bg-base-300"></span>{{ __('oder Code eingeben') }}<span class="h-px flex-1 bg-base-300"></span></div>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.two-factor.login.attempt') }}" class="space-y-4">
            @csrf
            <div x-show="authMode">
                <label class="block text-sm mb-1" for="code">{{ __('Code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                       placeholder="000000" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.4em]">
            </div>
            <div x-show="recovery" x-cloak>
                <label class="block text-sm mb-1" for="recovery_code">{{ __('Recovery-Code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       class="w-full border border-base-300 rounded px-3 py-2 bg-base-100">
            </div>
            <x-button type="submit" tone="primary" class="w-full">{{ __('Bestätigen') }}</x-button>
        </form>

        <button type="button" class="mt-3 w-full text-center text-sm text-primary hover:underline" x-on:click="toggle()">
            <span x-show="authMode">{{ __('Stattdessen Recovery-Code verwenden') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Zurück zum Authenticator-Code') }}</span>
        </button>

        @if ($hasEmail ?? false)
            <div class="mt-5 border-t border-base-300 pt-4">
                <p class="text-sm text-base-content/70 mb-2">{{ __('Oder Code per E-Mail erhalten:') }}</p>
                <form method="POST" action="{{ route('customer.two-factor.login.email') }}" class="mb-3">
                    @csrf
                    <x-button type="submit" tone="outline" size="sm" class="w-full">{{ __('Code per E-Mail senden') }}</x-button>
                </form>
                <form method="POST" action="{{ route('customer.two-factor.login.attempt') }}" class="space-y-2">
                    @csrf
                    <input name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('E-Mail-Code') }}"
                           class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.4em]">
                    <x-button type="submit" tone="primary" size="sm" class="w-full">{{ __('Mit E-Mail-Code bestätigen') }}</x-button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-base-content/60 hover:underline">← {{ __('Abbrechen') }}</button>
        </form>
    </div>
    @include('partials.webauthn-script')
@endsection

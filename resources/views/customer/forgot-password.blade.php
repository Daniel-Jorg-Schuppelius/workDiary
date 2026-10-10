{{--
  Created on   : Fri Oct 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : forgot-password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Passwort vergessen“ im Kundenportal (MVP-1096); die Bestätigung zeigt das Layout. --}}
@extends('customer.layout')

@section('content')
    <div class="max-w-md mx-auto bg-base-100 border border-base-300 rounded p-6 mt-10">
        <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
            <x-icon name="lock_reset" />
            {{ __('Passwort vergessen') }}
        </h1>
        <p class="mb-4 text-sm text-base-content/70">{{ __('Geben Sie Ihre E-Mail-Adresse ein – wir senden Ihnen einen Link zum Zurücksetzen.') }}</p>
        <form method="POST" action="{{ route('customer.password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm mb-1" for="email">{{ __('E-Mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       class="w-full border border-base-300 rounded px-3 py-2 bg-base-100">
                @error('email')
                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <x-button type="submit" tone="primary" class="w-full">{{ __('Link senden') }}</x-button>
        </form>
        <p class="mt-4 text-center text-sm">
            <a href="{{ route('customer.login') }}" class="link link-hover">{{ __('Zurück zur Anmeldung') }}</a>
        </p>
    </div>
@endsection

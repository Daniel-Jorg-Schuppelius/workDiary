{{--
  Created on   : Sat Jun 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : forgot-password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51). --}}
@extends('layouts.guest')

@section('title', __('Passwort vergessen'))
@section('headline', __('Passwort vergessen'))
@section('intro', __('Geben Sie Ihre E-Mail-Adresse ein – wir senden Ihnen einen Link zum Zurücksetzen.'))

@section('content')
    @if (session('status'))
        <div class="mb-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif

    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('E-Mail') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror"
                       placeholder="{{ __('name@firma.de') }}">
                @error('email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                ⇢ {{ __('Link senden') }}
            </x-button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/70">
        <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">← {{ __('Zurück zur Anmeldung') }}</a>
    </p>
@endsection

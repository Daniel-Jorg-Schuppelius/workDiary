{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sso-discover.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51). --}}
@extends('layouts.guest')

@section('title', __('Mit Single-Sign-on anmelden'))
@section('headline', __('Mit Single-Sign-on anmelden'))
@section('intro', __('sso.discover.hint'))

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        {{-- Bewusst GET: die E-Mail-Domain ist kein Geheimnis, der Flow bleibt lesezeichenfähig. --}}
        <form method="GET" action="{{ route('sso.discover') }}" class="space-y-5">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('sso.discover.email_label') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', request('email')) }}"
                    autofocus
                    autocomplete="email"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror"
                    placeholder="{{ __('sso.discover.email_placeholder') }}"
                >
                @error('email')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
                @error('org')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <x-button
                type="submit"
                tone="primary"
                class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
            >
                ⇢ {{ __('sso.discover.submit') }}
            </x-button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/70">
        <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">← {{ __('sso.discover.back_to_login') }}</a>
    </p>
@endsection

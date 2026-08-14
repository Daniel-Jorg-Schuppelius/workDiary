{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : invitation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einladungs-Annahme (MVP-510) — erwartet: $portalUser, $token --}}
@extends('customer.layout')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-xl font-bold">{{ __('Passwort festlegen') }}</h1>
            <p class="mb-4 text-sm text-base-content/70">
                {{ __('Hallo :name — lege das Passwort für deinen Portalzugang fest. Danach meldest du dich mit :email an.', ['name' => $portalUser->name, 'email' => $portalUser->email]) }}
            </p>

            <form method="POST" action="{{ route('customer.invitation.accept', ['token' => $token]) }}" class="space-y-3">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-label" for="invite-password">{{ __('Neues Passwort') }}</label>
                    <input id="invite-password" name="password" type="password" required autocomplete="new-password"
                           class="input input-bordered w-full">
                    @error('password')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-label" for="invite-password-confirm">{{ __('Passwort wiederholen') }}</label>
                    <input id="invite-password-confirm" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="input input-bordered w-full">
                </div>

                <x-button type="submit" tone="primary" icon="check" class="w-full">
                    <span>{{ __('Passwort speichern') }}</span>
                </x-button>
            </form>
        </div>
    </div>
@endsection

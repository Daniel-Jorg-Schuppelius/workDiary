{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : confirm-password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Passwortbestätigung vor heiklen Kontoaktionen (Sicherheitsaudit 2026-09-17,
  authflow-1). SSO-pflichtige Konten haben kein Passwort — für sie führt der
  Weg über eine erneute Anmeldung beim Identitätsanbieter.
--}}
@extends('layouts.guest')

@section('title', __('Bestätigung'))
@section('headline', __('Passwort bestätigen'))

@section('header-action')
@endsection

@section('intro')
    <p>{{ __('Diese Aktion ändert Ihre Anmeldemittel. Bitte bestätigen Sie sie mit Ihrem Passwort.') }}</p>
@endsection

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <x-validation-errors first class="mb-4" />

        @if ($ssoSlug !== null)
            <p class="mb-4 text-sm text-base-content/70">{{ __('Ihr Konto meldet sich über den Identitätsanbieter an. Bitte melden Sie sich dort erneut an.') }}</p>
            <x-button tag="a" :href="route('sso.start', ['slug' => $ssoSlug])" tone="primary"
                      class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold" icon="login">{{ __('Erneut anmelden') }}</x-button>
        @else
            <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" autofocus required
                           class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                </div>

                <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                    {{ __('Bestätigen') }}
                </x-button>
            </form>
        @endif
    </div>
@endsection

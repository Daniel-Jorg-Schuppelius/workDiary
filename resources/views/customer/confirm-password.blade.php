{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : confirm-password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Portal-Gegenstück zu auth/confirm-password (Sicherheitsaudit 2026-09-17,
  authflow-1): Passkey-Registrierung im Portal verlangt das Passwort.
--}}
@extends('customer.layout')

@section('content')
    <div class="max-w-md mx-auto mt-8 space-y-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-icon name="lock" />
            {{ __('Passwort bestätigen') }}
        </h1>

        <x-validation-errors first />

        <div class="rounded-box border border-base-300 bg-base-100 p-4 space-y-4">
            <p class="text-sm text-base-content/70">{{ __('Diese Aktion ändert Ihre Anmeldemittel. Bitte bestätigen Sie sie mit Ihrem Passwort.') }}</p>
            <form method="POST" action="{{ route('customer.password.confirm.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">{{ __('Passwort') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" autofocus required
                           class="input input-bordered w-full">
                </div>
                <x-button type="submit" tone="primary" icon="check" class="w-full">{{ __('Bestätigen') }}</x-button>
            </form>
        </div>
    </div>
@endsection

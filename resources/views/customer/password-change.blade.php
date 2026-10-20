{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : password-change.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Passwort ändern (Phase 153, MVP-1233) — erwartet: $user, $forced (Pflichtwechsel nach Startpasswort). --}}
@extends('customer.layout')

@section('content')
    <div class="mx-auto max-w-md space-y-4">
        <x-page-toolbar :title="__('customer_portal.password_change.title')" :back="$forced ? null : route('customer.profile.show')" :back-label="__('Profil')" />
        <x-card padding="p-6">
            @if ($forced)
                <div role="status" class="alert alert-warning mb-4 text-sm">{{ __('customer_portal.password_change.forced_hint') }}</div>
            @endif
            <p class="mb-4 text-sm text-base-content/70">{{ __('customer_portal.password_change.intro') }}</p>

            <x-validation-errors first />

            <form method="POST" action="{{ route('customer.password.change.update') }}" class="space-y-3">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-label" for="password-current">{{ __('customer_portal.password_change.current') }}</label>
                    <input id="password-current" name="current_password" type="password" required autocomplete="current-password"
                           class="input input-bordered w-full">
                    @error('current_password')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-label" for="password-new">{{ __('customer_portal.password_change.new') }}</label>
                    <input id="password-new" name="password" type="password" required autocomplete="new-password"
                           class="input input-bordered w-full">
                    @error('password')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-label" for="password-new-confirm">{{ __('customer_portal.password_change.confirm') }}</label>
                    <input id="password-new-confirm" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="input input-bordered w-full">
                </div>

                <x-button type="submit" tone="primary" icon="check" class="w-full">
                    <span>{{ __('customer_portal.password_change.submit') }}</span>
                </x-button>
            </form>
        </x-card>
    </div>
@endsection

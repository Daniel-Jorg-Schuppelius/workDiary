{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : profile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Profil + E-Mail-Selbständerung (MVP-712) — erwartet: $user, $pendingEmail, $pendingRequestedAt, $ttlHours --}}
@extends('customer.layout')

@section('content')
    <div class="max-w-2xl mx-auto mt-8 space-y-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-icon name="account_circle" />
            {{ __('Profil') }}
        </h1>

        <x-validation-errors first />

        <div class="border border-base-300 bg-base-100 rounded p-4">
            <p class="font-semibold">{{ __('Ihr Zugang') }}</p>
            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                <dt class="text-muted">{{ __('Name') }}</dt>
                <dd>{{ $user->name }}</dd>
                <dt class="text-muted">{{ __('Anmelde-E-Mail') }}</dt>
                <dd>{{ $user->email }}</dd>
                <dt class="text-muted">{{ __('Kunde') }}</dt>
                <dd>{{ $user->customer?->name ?? '—' }}</dd>
            </dl>
        </div>

        <div class="border border-base-300 bg-base-100 rounded p-4">
            <p class="font-semibold">{{ __('E-Mail-Adresse ändern') }}</p>
            <p class="mt-1 text-sm text-muted">
                {{ __('Wir senden einen Bestätigungslink an die neue Adresse. Erst nach dem Klick wird sie zur Anmelde-E-Mail; Ihre bisherige Adresse erhält eine Information.') }}
            </p>

            @if ($pendingEmail !== null && $pendingRequestedAt !== null && ! $pendingRequestedAt->copy()->addHours($ttlHours)->isPast())
                <div role="status" class="alert alert-warning mt-3 text-sm">
                    {{ __('Für :email steht eine Bestätigung aus (gültig bis :until). Sie können die Änderung mit einer neuen Anfrage überschreiben.', ['email' => $pendingEmail, 'until' => $pendingRequestedAt->copy()->addHours($ttlHours)->fdatetime()]) }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.profile.email.request') }}" class="mt-3 space-y-3">
                @csrf
                <x-input-field name="email" type="email" :label="__('Neue E-Mail-Adresse')" :value="old('email')" required />
                <div class="flex justify-end">
                    <x-button type="submit" tone="primary" icon="mail"><span>{{ __('Bestätigungslink senden') }}</span></x-button>
                </div>
            </form>
        </div>
    </div>
@endsection

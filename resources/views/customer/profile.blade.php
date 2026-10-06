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
            <x-detail-grid class="mt-2">
                <x-detail-grid.row :label="__('Name')">{{ $user->name }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Anmelde-E-Mail')">{{ $user->email }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kunde')">{{ $user->customer?->name ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
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

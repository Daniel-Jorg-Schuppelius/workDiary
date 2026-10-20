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
    @php($portalPreviewActive = app(\App\Services\CustomerPortal\PortalPreview::class)->isActive(request()))
    <div class="max-w-2xl mx-auto space-y-4">
        <x-page-toolbar :title="__('Profil')" :subtitle="__('customer_portal.profile.subtitle')" />

        <x-validation-errors first />

        <x-card :title="__('Ihr Zugang')" icon="badge">
            <x-detail-grid class="mt-2">
                <x-detail-grid.row :label="__('Name')">{{ $user->name }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Anmelde-E-Mail')">{{ $user->email }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kunde')">{{ $user->customer?->name ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>

        @unless ($portalPreviewActive)
            {{-- Darstellung (Phase 154, MVP-1241): Farbschema wie der Kopfzeilen-Umschalter, Sprache wie intern. --}}
            @php($portalScheme = (string) (((array) ($user->preferences ?? []))['color_scheme'] ?? 'auto'))
            <x-card :title="__('customer_portal.appearance.title')" icon="palette">
                <p class="text-sm text-muted">{{ __('customer_portal.appearance.intro') }}</p>
                <form method="POST" action="{{ route('customer.theme.update') }}" class="mt-3 space-y-3">
                    @csrf
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('customer_portal.appearance.scheme') }}</legend>
                        <div class="flex flex-wrap gap-4">
                            @foreach (['auto' => 'customer_portal.appearance.scheme_auto', 'light' => 'customer_portal.appearance.scheme_light', 'dark' => 'customer_portal.appearance.scheme_dark'] as $scheme => $labelKey)
                                <label class="label cursor-pointer gap-2">
                                    <input type="radio" name="scheme" value="{{ $scheme }}" class="radio radio-sm" @checked($portalScheme === $scheme)>
                                    <span>{{ __($labelKey) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="flex justify-end">
                        <x-button type="submit" tone="primary" icon="check"><span>{{ __('customer_portal.appearance.save') }}</span></x-button>
                    </div>
                </form>
                <div class="mt-3 border-t border-base-300 pt-3">
                    <p class="mb-2 text-sm font-medium">{{ __('customer_portal.appearance.language') }}</p>
                    <x-locale-switcher variant="inline" />
                </div>
            </x-card>

            {{-- Passwort ändern (Phase 153, MVP-1233); in der Vorschau als Kunde gesperrt. --}}
            <x-card :title="__('customer_portal.password_change.profile_heading')" icon="password">
                <p class="text-sm text-muted">{{ __('customer_portal.password_change.profile_intro') }}</p>
                <div class="mt-3 flex justify-end">
                    <x-button :href="route('customer.password.change')" tone="ghost" icon="password"><span>{{ __('customer_portal.password_change.profile_link') }}</span></x-button>
                </div>
            </x-card>
        @endunless

        <x-card :title="__('E-Mail-Adresse ändern')" icon="mail">
            <p class="text-sm text-muted">
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
        </x-card>
    </div>
@endsection

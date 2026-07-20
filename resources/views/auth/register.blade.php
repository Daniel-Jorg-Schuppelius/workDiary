{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51); das frühere
     hartkodierte „WorkDiary Next"-Branding läuft jetzt über die Brand-Logik
     des Layouts (BrandingService bzw. app.name-Fallback). --}}
@extends('layouts.guest')

@section('title', __('Registrieren'))
@section('headline', __('Organisation registrieren'))
@section('intro', __('Legen Sie Ihre Organisation und Ihren Administrator-Account an.'))

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            {{-- Organisation --}}
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-base-content/50">{{ __('Organisation') }}</p>
                <label for="org_name" class="mb-2 block text-sm font-medium text-base-content">{{ __('Name der Organisation') }}</label>
                <input
                    id="org_name"
                    name="org_name"
                    type="text"
                    value="{{ old('org_name') }}"
                    autocomplete="organization"
                    autofocus
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('org_name') ring-2 ring-error/40 @enderror"
                    placeholder="{{ __('z. B. Klimatechnik Mustermann GmbH') }}"
                >
                @error('org_name')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="divider text-xs text-base-content/40">{{ __('Administrator-Account') }}</div>

            {{-- Name --}}
            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-base-content">{{ __('Vollständiger Name') }}</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('name') ring-2 ring-error/40 @enderror"
                    placeholder="{{ __('Max Mustermann') }}"
                >
                @error('name')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- E-Mail --}}
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('E-Mail-Adresse') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror"
                    placeholder="admin@example.com"
                >
                @error('email')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort --}}
            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('password') ring-2 ring-error/40 @enderror"
                    placeholder="{{ __('Mindestens 8 Zeichen') }}"
                >
                @error('password')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort bestätigen --}}
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort bestätigen') }}</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25"
                    placeholder="{{ __('Passwort wiederholen') }}"
                >
            </div>

            <x-button
                type="submit"
                tone="primary"
                class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
            >
                ⇢ {{ __('Organisation anlegen') }}
            </x-button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/70">
        {{ __('Bereits registriert?') }}
        <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">{{ __('Anmelden') }}</a>
    </p>
@endsection

{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51). --}}
@extends('layouts.guest')

@section('title', __('Anmelden'))
@section('headline', __('Anmelden'))
@section('intro', __('Benutzerdaten aus dem bestehenden Auftragsbuch-System.'))

@section('header-action')
    <x-button href="{{ route('home') }}" tone="ghost" size="sm" class="gap-1" icon="home">{{ __('Startseite') }}</x-button>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif

    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="username" class="mb-2 block text-sm font-medium text-base-content">{{ __('Benutzername') }}</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    value="{{ old('username') }}"
                    autocomplete="username"
                    autofocus
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('username') ring-2 ring-error/40 @enderror"
                    placeholder="{{ __('Benutzername') }}"
                >
                @error('username')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25"
                    placeholder="{{ __('Passwort') }}"
                >
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-3">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="checkbox checkbox-primary checkbox-sm"
                    >
                    <span class="text-sm text-base-content/80">{{ __('Angemeldet bleiben') }}</span>
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-primary transition hover:opacity-80">{{ __('Passwort vergessen?') }}</a>
            </div>

            <x-button
                type="submit"
                tone="primary"
                class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
            >
                ⇢ {{ __('Anmelden') }}
            </x-button>
        </form>

        {{-- Single-Sign-on (Feature 057): Einstieg über die Organisations-Kennung. --}}
        <p class="mt-4 text-center text-sm text-base-content/70">
            <a href="{{ route('sso.discover') }}" class="text-primary transition hover:opacity-80">{{ __('Mit Single-Sign-on anmelden') }}</a>
        </p>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/70">
        <a href="{{ route('home') }}" class="text-primary transition hover:opacity-80">← {{ __('Zurück zur Startseite') }}</a>
    </p>
    @if (config('app.registration_enabled'))
    <p class="mt-3 text-center text-sm text-base-content/70">
        {{ __('Noch kein Account?') }}
        <a href="{{ route('register') }}" class="text-primary transition hover:opacity-80">{{ __('Organisation registrieren') }}</a>
    </p>
    @endif
@endsection

{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51). Das frühere
     Inline-Toggle-Skript entfällt bewusst: layout.js (app.js) steuert den
     Theme-Toggle zentral — ein zweiter Click-Handler schaltete doppelt. --}}
@extends('layouts.guest')

@section('title', __('Passwort ändern'))
@section('headline', __('Passwort ändern'))

@section('header-action')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button type="submit" tone="ghost" size="sm" class="gap-1" icon="logout">{{ __('Abmelden') }}</x-button>
    </form>
@endsection

@section('intro')
    {{ $mustChange
        ? __('Bitte legen Sie ein neues Passwort fest, bevor Sie weiterarbeiten.')
        : __('Wählen Sie ein neues, sicheres Passwort für Ihr Konto.') }}
@endsection

@section('content')
    @if (session('warning'))
        <div class="mb-4 alert alert-warning text-sm">{{ session('warning') }}</div>
    @endif

    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5">
            @csrf

            @unless ($mustChange)
                <div>
                    <label for="current_password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Aktuelles Passwort') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                           class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('current_password') ring-2 ring-error/40 @enderror">
                    @error('current_password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>
            @endunless

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Neues Passwort') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('password') ring-2 ring-error/40 @enderror">
                @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-base-content">{{ __('Bestätigen') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
            </div>

            <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                ⇢ {{ __('Speichern') }}
            </x-button>
        </form>
    </div>
@endsection

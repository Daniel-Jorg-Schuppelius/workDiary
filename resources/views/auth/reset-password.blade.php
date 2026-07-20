{{-- Guest-Layout statt Standalone-Skelett (Vollaudit 2026-07, M51). --}}
@extends('layouts.guest')

@section('title', __('Passwort zurücksetzen'))
@section('headline', __('Passwort zurücksetzen'))
@section('intro', __('Wählen Sie ein neues, sicheres Passwort.'))

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('E-Mail') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror">
                @error('email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>

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
                ⇢ {{ __('Passwort speichern') }}
            </x-button>
        </form>
    </div>
@endsection

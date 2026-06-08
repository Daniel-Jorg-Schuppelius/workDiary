@extends('customer.layout')

@section('content')
    <div class="max-w-md mx-auto bg-base-100 border border-base-300 rounded p-6 mt-10" x-data="twoFactorChallenge">
        <h1 class="text-xl font-semibold mb-2 flex items-center gap-2">
            <span class="material-symbols-outlined">verified_user</span>
            {{ __('Zwei-Faktor-Bestätigung') }}
        </h1>
        <p class="text-sm text-base-content/70 mb-4" x-show="authMode">{{ __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.') }}</p>
        <p class="text-sm text-base-content/70 mb-4" x-show="recovery" x-cloak>{{ __('Geben Sie einen Ihrer Recovery-Codes ein.') }}</p>

        @if ($errors->any())
            <div class="alert alert-error text-sm mb-4">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('customer.two-factor.login.attempt') }}" class="space-y-4">
            @csrf
            <div x-show="authMode">
                <label class="block text-sm mb-1" for="code">{{ __('Code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                       placeholder="000000" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.4em]">
            </div>
            <div x-show="recovery" x-cloak>
                <label class="block text-sm mb-1" for="recovery_code">{{ __('Recovery-Code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       class="w-full border border-base-300 rounded px-3 py-2 bg-base-100">
            </div>
            <button type="submit" class="btn btn-primary w-full">{{ __('Bestätigen') }}</button>
        </form>

        <button type="button" class="mt-3 w-full text-center text-sm text-primary hover:underline" x-on:click="toggle()">
            <span x-show="authMode">{{ __('Stattdessen Recovery-Code verwenden') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Zurück zum Authenticator-Code') }}</span>
        </button>

        <form method="POST" action="{{ route('customer.logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-base-content/60 hover:underline">← {{ __('Abbrechen') }}</button>
        </form>
    </div>
@endsection

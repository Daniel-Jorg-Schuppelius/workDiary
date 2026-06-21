@extends('customer.layout')

@section('content')
    <div class="max-w-md mx-auto bg-base-100 border border-base-300 rounded p-6 mt-10">
        <h1 class="text-xl font-semibold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">login</span>
            {{ __('Anmelden') }}
        </h1>
        <form method="POST" action="{{ route('customer.login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm mb-1" for="email">{{ __('E-Mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="w-full border border-base-300 rounded px-3 py-2 bg-base-100">
                @error('email')
                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm mb-1" for="password">{{ __('Passwort') }}</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="w-full border border-base-300 rounded px-3 py-2 bg-base-100">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="checkbox checkbox-sm">
                <span>{{ __('Angemeldet bleiben') }}</span>
            </label>
            <x-button type="submit" tone="primary" class="w-full">{{ __('Anmelden') }}</x-button>
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('title', __('Passwort ändern'))

@section('content')
<div class="mx-auto max-w-md space-y-4">
    <h1 class="text-2xl font-semibold">{{ __('Passwort ändern') }}</h1>

    @if ($mustChange)
        <div class="alert alert-warning">
            <span>{{ __('Bitte legen Sie ein neues Passwort fest, bevor Sie weiterarbeiten.') }}</span>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
        @csrf

        @unless ($mustChange)
            <div>
                <label class="label" for="current_password">
                    <span class="label-text">{{ __('Aktuelles Passwort') }}</span>
                </label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="input input-bordered w-full" required>
                @error('current_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endunless

        <div>
            <label class="label" for="password">
                <span class="label-text">{{ __('Neues Passwort') }}</span>
            </label>
            <input type="password" id="password" name="password" autocomplete="new-password" class="input input-bordered w-full" required>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">
                <span class="label-text">{{ __('Neues Passwort bestätigen') }}</span>
            </label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="input input-bordered w-full" required>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', __('Profil'))

@section('content')
<div class="mx-auto max-w-md space-y-4">
    <h1 class="text-2xl font-semibold">{{ __('Profil') }}</h1>

    <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
        @csrf
        @method('PUT')

        <div>
            <label class="label" for="name">
                <span class="label-text">{{ __('Name') }}</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="email">
                <span class="label-text">{{ __('E-Mail') }}</span>
            </label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('account.password.edit') }}" class="btn btn-ghost">{{ __('Passwort ändern') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</div>
@endsection

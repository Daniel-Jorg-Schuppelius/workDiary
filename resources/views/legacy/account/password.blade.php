@extends('layouts.app')
@section('title', __('Passwort ändern') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Passwort'))

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="rounded-box border border-base-300 bg-base-100 p-5">
            <form method="POST" action="{{ route('legacy.account.password.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="current_password" class="label text-sm font-semibold pb-1">{{ __('Altes Passwort') }}</label>
                    <input id="current_password" name="current_password" type="password" class="input input-bordered input-sm w-full @error('current_password') input-error @enderror" required>
                    @error('current_password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort') }}</label>
                    <input id="password" name="password" type="password" class="input input-bordered input-sm w-full @error('password') input-error @enderror" required>
                    @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort (Wiederholung)') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered input-sm w-full" required>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Ändern') }}</button>
                    <a href="{{ route('legacy.diary.week') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

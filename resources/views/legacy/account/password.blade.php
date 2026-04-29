@extends('layouts.app')
@section('title', 'Passwort ändern — WorkDiary')
@section('nav-title', 'Passwort')

@section('content')
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-xl flex-col">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100">
            <div class="h-full overflow-auto p-5">
            <form method="POST" action="{{ route('legacy.account.password.update') }}" class="space-y-4">
                @csrf

                <div class="flex flex-col">
                    <label for="current_password" class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">Altes Passwort</span></label>
                    <input id="current_password" name="current_password" type="password" class="input input-bordered input-sm w-full @error('current_password') input-error @enderror" required>
                    @error('current_password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col">
                    <label for="password" class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Neues Passwort') }}</span></label>
                    <input id="password" name="password" type="password" class="input input-bordered input-sm w-full @error('password') input-error @enderror" required>
                    @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col">
                    <label for="password_confirmation" class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">Neues Passwort (Wiederholung)</span></label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered input-sm w-full" required>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Ändern') }}</button>
                    <a href="{{ route('legacy.diary.week') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection

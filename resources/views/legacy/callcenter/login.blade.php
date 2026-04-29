@extends('layouts.app')
@section('title', __('Callcenter Login') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Callcenter Login'))

@section('content')
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-md flex-col">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100">
            <div class="h-full overflow-auto p-5">
            <h2 class="mb-3 text-base font-bold">{{ __('Notdienstplan Login') }}</h2>
            <form method="POST" action="{{ route('legacy.callcenter.login.submit') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="username" class="label text-sm font-semibold pb-1">{{ __('Nutzer') }}</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" class="input input-bordered input-sm w-full @error('username') input-error @enderror" required>
                    @error('username')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="label text-sm font-semibold pb-1">{{ __('Passwort') }}</label>
                    <input id="password" name="password" type="password" class="input input-bordered input-sm w-full" required>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Anmelden') }}</button>
                    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">Zurueck</a>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection

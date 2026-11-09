{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : login.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.guest')

@section('title', __('legacy_support.portal.title'))
@section('headline', __('legacy_support.portal.title'))
@section('intro', __('legacy_support.portal.login_intro'))
@section('header-action')
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 alert alert-success text-sm" role="status">{{ session('status') }}</div>
    @endif

    <x-auth-card>
        <form method="POST" action="{{ route('legacy.portal.login.submit') }}" class="space-y-5">
            @csrf
            <div>
                <label for="username" class="mb-2 block text-sm font-medium text-base-content">{{ __('legacy_support.field.username') }}</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autofocus required maxlength="100"
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('username') ring-2 ring-error/40 @enderror">
                @error('username')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('legacy_support.field.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="255"
                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('password') ring-2 ring-error/40 @enderror">
                @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                ⇢ {{ __('legacy_support.portal.login') }}
            </x-button>
        </form>
    </x-auth-card>
@endsection

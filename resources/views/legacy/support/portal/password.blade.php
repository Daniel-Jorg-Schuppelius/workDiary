{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.public')

@section('title', __('legacy_support.portal.change_password'))
@section('header-action')
    @include('legacy.support.portal._logout')
@endsection

@section('content')
    @php $inputClass = 'input input-bordered w-full'; @endphp
    @include('legacy.support.portal._frame', ['customer' => $customer, 'active' => 'password'])

    <form method="POST" action="{{ route('legacy.portal.password.update') }}" class="mx-auto max-w-md space-y-4 rounded-box border border-base-300 bg-base-100 p-5 shadow-xs">
        @csrf
        @method('PUT')
        <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ __('legacy_support.portal.change_password') }}</h2>

        <div>
            <label for="portal-current-password" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.current_password') }}</label>
            <input id="portal-current-password" name="current_password" type="password" autocomplete="current-password" class="{{ $inputClass }} @error('current_password') input-error @enderror" required>
            @error('current_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="portal-password" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.new_password') }}</label>
            <input id="portal-password" name="password" type="password" minlength="4" maxlength="10" autocomplete="new-password" class="{{ $inputClass }} @error('password') input-error @enderror" required>
            <p class="mt-1 text-xs text-muted">{{ __('legacy_support.customers.password_hint') }}</p>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="portal-password-confirmation" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.password_repeat') }}</label>
            <input id="portal-password-confirmation" name="password_confirmation" type="password" maxlength="10" autocomplete="new-password" class="{{ $inputClass }}" required>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-icon-btn icon="lock" tone="primary" size="sm" type="submit" show-label>{{ __('legacy_support.portal.change_password') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('legacy.portal.orders.index')" show-label>{{ __('legacy_support.portal.back_to_list') }}</x-icon-btn>
        </div>
    </form>
@endsection

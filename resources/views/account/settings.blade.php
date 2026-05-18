@extends('layouts.app')

@section('title', __('Profil & Einstellungen'))
@section('nav-title', __('Profil & Einstellungen'))

@section('content')
@php
    /** @var \App\Models\User $user */
    $prefs = $user->preferences();
    $themes = (array) config('personalization.themes', []);
    $startpages = (array) config('personalization.startpages', []);
    $avatarMaxKb = (int) config('branding.limits.avatar_kb', 1024);
    $avatarHelper = __('PNG, JPG oder WEBP. Max. :max KB.', ['max' => $avatarMaxKb]);
@endphp

<x-page-shell gap="6">
    @if (session('success'))
        <div class="alert alert-success">
            <x-icon name="check_circle" /> <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- ── Avatar ───────────────────────────────────────────────── --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">
                <x-icon name="account_circle" />
                {{ __('Profilbild') }}
            </h2>
            <x-file-upload
                :label="__('Avatar')"
                :action="route('attachments.store', ['type' => 'user', 'id' => $user->id])"
                :delete-action="route('attachments.destroyMeta', ['type' => 'user', 'id' => $user->id, 'meta' => 'avatar'])"
                :current="$user->avatar()"
                :meta="\App\Models\Attachment::META_AVATAR"
                :max-kb="$avatarMaxKb"
                :helper="$avatarHelper"
            />
        </div>
    </div>

    {{-- ── Profildaten ──────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('account.profile.update') }}">
        @csrf
        @method('PUT')
        <x-form-group :legend="__('Profildaten')" icon="person" tone="info" cols="2">
            <div class="fieldset">
                <label class="fieldset-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
                @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label" for="email">{{ __('E-Mail') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
                @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </x-form-group>
        <div class="flex justify-end mt-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Speichern') }}</button>
        </div>
    </form>

    {{-- ── Präferenzen ──────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('account.preferences.update') }}">
        @csrf
        @method('PUT')
        <x-form-group :legend="__('Persönliche Präferenzen')" icon="tune" tone="ghost" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Theme') }}</label>
                <select name="preferences[theme]" class="select select-bordered w-full">
                    @foreach ($themes as $theme)
                        <option value="{{ $theme }}" @selected(old('preferences.theme', $prefs['theme'] ?? '') === $theme)>{{ ucfirst($theme) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Sprache') }}</label>
                <input type="text" name="preferences[locale]" maxlength="10" placeholder="de"
                       class="input input-bordered w-full"
                       value="{{ old('preferences.locale', $prefs['locale'] ?? '') }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Datumsformat') }}</label>
                <input type="text" name="preferences[date_format]" maxlength="32"
                       class="input input-bordered w-full"
                       placeholder="{{ config('personalization.defaults.date_format') }}"
                       value="{{ old('preferences.date_format', $prefs['date_format'] ?? '') }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Uhrzeitformat') }}</label>
                <input type="text" name="preferences[time_format]" maxlength="32"
                       class="input input-bordered w-full"
                       placeholder="{{ config('personalization.defaults.time_format') }}"
                       value="{{ old('preferences.time_format', $prefs['time_format'] ?? '') }}">
            </div>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('Startseite nach dem Login') }}</label>
                <select name="preferences[startpage]" class="select select-bordered w-full">
                    <option value="">{{ __('Standard') }}</option>
                    @foreach ($startpages as $route)
                        <option value="{{ $route }}" @selected(old('preferences.startpage', $prefs['startpage'] ?? '') === $route)>{{ $route }}</option>
                    @endforeach
                </select>
            </div>
        </x-form-group>
        <div class="flex justify-end mt-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ __('Präferenzen speichern') }}</button>
        </div>
    </form>

    {{-- ── Passwort ─────────────────────────────────────────────── --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">
                <x-icon name="lock" />
                {{ __('Passwort') }}
            </h2>
            <p class="text-sm opacity-70">
                {{ __('Das Passwort wird in einem separaten Dialog geändert.') }}
            </p>
            <div class="card-actions justify-end">
                <a href="{{ route('account.password.edit') }}" class="btn btn-outline btn-sm">
                    <x-icon name="lock_reset" /> {{ __('Passwort ändern') }}
                </a>
            </div>
        </div>
    </div>
</x-page-shell>
@endsection

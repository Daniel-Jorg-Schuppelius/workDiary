@extends('layouts.app')
@section('title', ($isEdit ? __('Mitarbeiter bearbeiten') : __('Mitarbeiter anlegen')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Legacy') . ' / ' . __('Mitarbeiter bearbeiten') : __('Legacy') . ' / ' . __('Mitarbeiter anlegen'))

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="rounded-box border border-base-300 bg-base-100 p-5">
            <form method="POST" action="{{ $isEdit ? route('legacy.users.update', $legacyUser) : route('legacy.users.store') }}" class="space-y-4">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div>
                    <label for="uname" class="label text-sm font-semibold pb-1">{{ __('Name') }}</label>
                    <input id="uname" name="uname" type="text" value="{{ old('uname', $legacyUser?->uname) }}" class="input input-bordered input-sm w-full @error('uname') input-error @enderror" required>
                    @error('uname')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="userpw" class="label text-sm font-semibold pb-1">
                        {{ __('Passwort') }}
                        @if ($isEdit)<span class="text-xs font-normal text-base-content/50"> — {{ __('leer lassen um beizubehalten') }}</span>@endif
                    </label>
                    <input id="userpw" name="userpw" type="password" value="{{ old('userpw') }}" autocomplete="new-password" class="input input-bordered input-sm w-full @error('userpw') input-error @enderror" @if (!$isEdit) required @endif>
                    @error('userpw')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="label text-sm font-semibold pb-1">{{ __('E-Mail') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $legacyUser?->email) }}" class="input input-bordered input-sm w-full @error('email') input-error @enderror">
                    @error('email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
                    <a href="{{ route('legacy.users.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : delete.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachfrage vor dem Löschen wie im Altportal; hängen noch Daten am Mitarbeiter, bleibt er. Erwartet: $legacyUser, $blockedBy (orders|oncall|notdienst|null) --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.users.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.users.title') }}</h1>

    @if ($blockedBy !== null)
        <p class="kp-alert kp-alert--error" role="alert">{{ __('legacy_classic.users.blocked.' . $blockedBy, ['name' => $legacyUser->uname]) }}</p>
        <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.users.index') }}">{{ __('legacy_classic.users.back_to_admin') }}</a></p>
    @else
        <p>{{ __('legacy_classic.users.confirm_delete', ['name' => $legacyUser->uname]) }}</p>
        <form method="POST" action="{{ route('legacy.users.destroy', $legacyUser) }}" class="kp-msg">
            @csrf
            @method('DELETE')
            <button type="submit" class="kp-danger">{{ __('legacy_classic.yes') }}</button>
            <a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.users.index') }}">{{ __('legacy_classic.no') }}</a>
        </form>
    @endif
@endsection

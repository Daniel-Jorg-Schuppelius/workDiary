{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : login.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anmeldung der Kunden wie im Altportal (index.php, login.php); die Überschrift kommt aus LEGACY_SUPPORT_TITLE. --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.classic.portal'))

@section('content')
    <h1>{{ filled(config('app.legacy_support_title')) ? config('app.legacy_support_title') : __('legacy_support.classic.portal') }}</h1>
    <p>{{ __('legacy_support.classic.login_hint') }}</p>
    @include('legacy.classic._flash')

    <form method="POST" action="{{ route('legacy.portal.login.submit') }}">
        @csrf
        <table class="eintrag">
            <tr>
                <td class="kp-label"><label for="kp-username">{{ __('legacy_support.classic.label.user') }}</label></td>
                <td><input id="kp-username" type="text" name="username" value="{{ old('username') }}" maxlength="100" autocomplete="username" autofocus required></td>
            </tr>
            <tr>
                <td class="kp-label"><label for="kp-password">{{ __('legacy_support.classic.label.password') }}</label></td>
                <td><input id="kp-password" type="password" name="password" maxlength="255" autocomplete="current-password" required></td>
            </tr>
            <tr><td colspan="2" class="kp-center"><button type="submit">{{ __('legacy_support.classic.login') }}</button></td></tr>
        </table>
    </form>
@endsection

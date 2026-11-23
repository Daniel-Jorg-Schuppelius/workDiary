{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : login.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anmeldung zum Notdienstplan wie im Altportal (callcenter/login.php). --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_classic.callcenter.login_title'))
@section('bodyClass', 'tb')

@section('content')
    <h1>{{ __('legacy_classic.callcenter.title') }}</h1>
    @if (filled(config('app.legacy_callcenter_subtitle')))
        <p>{{ config('app.legacy_callcenter_subtitle') }}</p>
    @endif
    @include('legacy.classic._flash')
    <form method="POST" action="{{ route('legacy.callcenter.login.submit') }}">
        @csrf
        <table class="eintrag">
            <tr>
                <td class="rechts"><label for="cc-username">{{ __('legacy_classic.label.user') }}</label></td>
                <td><input id="cc-username" type="text" name="username" value="{{ old('username') }}" autocomplete="username" required></td>
            </tr>
            <tr>
                <td class="rechts"><label for="cc-password">{{ __('legacy_classic.label.password') }}</label></td>
                <td><input id="cc-password" type="password" name="password" autocomplete="current-password" required></td>
            </tr>
            <tr><td colspan="2"><button type="submit">{{ __('legacy_classic.login') }}</button></td></tr>
        </table>
    </form>
@endsection

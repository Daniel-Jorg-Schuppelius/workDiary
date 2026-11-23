{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Passwort ändern wie im Altportal (zugang.php). --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.password.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.diary_title') }}</h1>

    <form method="POST" action="{{ route('legacy.account.password.update') }}">
        @csrf
        <table class="kp-msg">
            <tr>
                <td class="ohne"><label for="tb-current-password">{{ __('legacy_classic.password.current') }}</label></td>
                <td class="ohne"><input id="tb-current-password" type="password" name="current_password" size="12" autocomplete="current-password"></td>
            </tr>
            <tr><td class="ohne" colspan="2">&nbsp;</td></tr>
            <tr>
                <td class="ohne"><label for="tb-password">{{ __('legacy_classic.password.new') }}</label></td>
                <td class="ohne"><input id="tb-password" type="password" name="password" size="12" autocomplete="new-password"></td>
            </tr>
            <tr><td class="ohne" colspan="2">{{ __('legacy_classic.password.repeat_hint') }}</td></tr>
            <tr>
                <td class="ohne"><label for="tb-password-confirmation">{{ __('legacy_classic.password.new') }}<span class="kp-sr-only"> {{ __('legacy_classic.password.repeat_hint') }}</span></label></td>
                <td class="ohne"><input id="tb-password-confirmation" type="password" name="password_confirmation" size="12" autocomplete="new-password"></td>
            </tr>
            <tr><td class="ohne" colspan="2">&nbsp;</td></tr>
            <tr>
                <td class="ohne">&nbsp;</td>
                <td class="ohne kp-center"><button type="submit">{{ __('legacy_classic.action.change') }}</button></td>
            </tr>
        </table>
    </form>
@endsection

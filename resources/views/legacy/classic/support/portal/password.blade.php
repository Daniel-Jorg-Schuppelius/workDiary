{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : password.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Passwort des Kunden ändern wie die Maske des Altportals (admin/zugang.php). Erwartet: $customer --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.classic.password_page.title'))

@section('content')
    @include('legacy.classic.support._portal_menu', ['customer' => $customer])
    <h1>{{ __('legacy_support.classic.password_page.title') }}</h1>

    <form method="POST" action="{{ route('legacy.portal.password.update') }}">
        @csrf
        @method('PUT')
        <table class="eintrag">
            <tr><td class="kp-label"><label for="kp-old">{{ __('legacy_support.classic.password_page.old') }}</label></td><td><input id="kp-old" type="password" name="current_password" autocomplete="current-password" required></td></tr>
            <tr><td class="kp-label"><label for="kp-new">{{ __('legacy_support.classic.password_page.new') }}</label></td><td><input id="kp-new" type="password" name="password" maxlength="10" autocomplete="new-password" required></td></tr>
            <tr><td class="kp-label"><label for="kp-new2">{{ __('legacy_support.classic.password_page.repeat') }}</label></td><td><input id="kp-new2" type="password" name="password_confirmation" maxlength="10" autocomplete="new-password" required></td></tr>
            <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.password_page.submit') }}</button></td></tr>
        </table>
    </form>
@endsection

{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mitarbeiter anlegen und ändern wie im Altportal; das Passwort zweimal, leer bleibt es beim Ändern. Erwartet: $legacyUser, $isEdit --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.users.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.users.title') }}</h1>

    <form method="POST" action="{{ $isEdit ? route('legacy.users.update', $legacyUser) : route('legacy.users.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        <table class="kp-msg">
            <tr><td colspan="2" class="mitte">{{ $isEdit ? __('legacy_classic.users.edit_heading', ['name' => $legacyUser->uname]) : __('legacy_classic.users.new_heading') }}</td></tr>
            <tr>
                <td class="rechts"><label for="tb-uname">{{ __('legacy_classic.label.username') }}</label></td>
                <td><input id="tb-uname" type="text" name="uname" maxlength="100" value="{{ old('uname', $legacyUser?->uname) }}"></td>
            </tr>
            <tr>
                <td class="rechts"><label for="tb-userpw">{{ __('legacy_classic.label.password') }}</label></td>
                <td><input id="tb-userpw" type="password" name="userpw" maxlength="15" autocomplete="new-password"></td>
            </tr>
            <tr>
                <td class="rechts"><label for="tb-userpw-confirmation">{{ __('legacy_classic.label.repeat') }}</label></td>
                <td><input id="tb-userpw-confirmation" type="password" name="userpw_confirmation" maxlength="15" autocomplete="new-password"></td>
            </tr>
            @if ($isEdit)
                <tr><td class="ohne">&nbsp;</td><td class="ohne">{{ __('legacy_classic.users.password_keep') }}</td></tr>
            @endif
            <tr>
                <td class="rechts"><label for="tb-email">{{ __('legacy_classic.label.email') }}</label></td>
                <td><input id="tb-email" type="text" name="email" maxlength="255" value="{{ old('email', $legacyUser?->email) }}"></td>
            </tr>
            <tr>
                <td class="ohne">&nbsp;</td>
                <td class="ohne"><button type="submit">{{ $isEdit ? __('legacy_classic.action.change') : __('legacy_classic.action.create') }}</button></td>
            </tr>
        </table>
    </form>
    <p>&raquo; <a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.users.index') }}">{{ __('legacy_classic.users.back') }}</a> &laquo;</p>
@endsection

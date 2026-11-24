{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenzugang anlegen bzw. ändern wie im Altportal (admin/edituser.php). Erwartet: $customer, $isEdit --}}
@extends('legacy.classic.shell')
@section('title', $isEdit ? __('legacy_support.customers.edit') : __('legacy_support.customers.create'))

@section('content')
    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.customers.index') }}">{{ __('legacy_support.classic.to_list') }}</a></p>
    @include('legacy.classic._flash')

    <form method="POST" action="{{ $isEdit ? route('legacy.support.customers.update', $customer) : route('legacy.support.customers.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        <table class="eintrag kp-medium">
            <tr>
                <th><label for="kp-firm">{{ __('legacy_support.classic.col.firm') }}:</label></th>
                <th><input id="kp-firm" type="text" name="kunde" maxlength="255" value="{{ old('kunde', $customer?->kunde) }}" required></th>
            </tr>
            <tr>
                <th><label for="kp-usern">{{ __('legacy_support.classic.col.username') }}:</label></th>
                <th><input id="kp-usern" type="text" name="usern" maxlength="10" autocomplete="off" value="{{ old('usern', $customer?->usern) }}" required></th>
            </tr>
            <tr>
                <th><label for="kp-passw">{{ __('legacy_support.classic.col.password') }}:</label></th>
                <th><input id="kp-passw" type="password" name="passw" maxlength="10" autocomplete="new-password" @unless ($isEdit) required @endunless></th>
            </tr>
            <tr>
                <th><label for="kp-passw2">{{ __('legacy_support.classic.col.password') }}:</label></th>
                <th><input id="kp-passw2" type="password" name="passw_confirmation" maxlength="10" autocomplete="new-password" @unless ($isEdit) required @endunless></th>
            </tr>
            @if ($isEdit)
                <tr><td colspan="2" class="kp-center">{{ __('legacy_support.customers.password_keep_hint') }}</td></tr>
            @endif
            <tr>
                <th>{{ $isEdit ? __('legacy_support.classic.customers.save_question') : __('legacy_support.classic.customers.create_question') }}</th>
                <th><button type="submit">{{ __('legacy_support.classic.yes') }}</button></th>
            </tr>
        </table>
    </form>
@endsection

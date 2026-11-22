{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Neuer Auftrag des Kunden wie im Altportal (kunde/kp_new.php). Erwartet: $customer --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.portal.new_order'))

@section('content')
    @include('legacy.classic.support._portal_menu', ['customer' => $customer])
    <p>{{ __('legacy_support.classic.new_entry') }}</p>

    <form method="POST" action="{{ route('legacy.portal.orders.store') }}">
        @csrf
        <table class="eintrag">
            <tr><td class="kp-label">{{ __('legacy_support.classic.label.customer') }}</td><td>{{ $customer->kunde }}</td></tr>
            <tr><td class="kp-label"><label for="kp-kname">{{ __('legacy_support.classic.label.name') }}</label></td><td><input id="kp-kname" type="text" name="kname" maxlength="30" autocomplete="name" value="{{ old('kname') }}" required></td></tr>
            <tr><td class="kp-label"><label for="kp-telefon">{{ __('legacy_support.classic.label.phone') }}</label></td><td><input id="kp-telefon" type="tel" name="telefon" maxlength="30" autocomplete="tel" value="{{ old('telefon') }}"></td></tr>
            <tr><td class="kp-label"><label for="kp-objekt">{{ __('legacy_support.classic.label.object') }}</label></td><td><input id="kp-objekt" type="text" name="objekt" maxlength="255" value="{{ old('objekt') }}"></td></tr>
            <tr><td class="kp-label"><label for="kp-auftrag">{{ __('legacy_support.classic.label.order') }}</label></td><td><textarea id="kp-auftrag" name="auftrag" rows="5" required>{{ old('auftrag') }}</textarea></td></tr>
            <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_support.classic.save') }}</button></td></tr>
        </table>
    </form>
@endsection

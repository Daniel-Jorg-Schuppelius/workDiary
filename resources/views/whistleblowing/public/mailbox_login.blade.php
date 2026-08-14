{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mailbox_login.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('whistleblowing.public.layout')

@section('title', __('Postfach'))

@section('content')
    <section class="wb-card">
        <h2>{{ __('Geschütztes Postfach') }}</h2>
        <p>{{ __('Geben Sie Ihr Geheimnis ein, um den Stand Ihrer Meldung zu sehen und mit der Meldestelle zu kommunizieren. Die Fallnummer ist KEIN Zugang.') }}</p>

        @if ($errors->any())
            <p class="wb-error">{{ $errors->first('secret') }}</p>
        @endif

        <form method="post" action="{{ route('whistleblowing.mailbox.authenticate') }}">
            @csrf
            <label for="secret">{{ __('Geheimnis') }}</label>
            <input id="secret" name="secret" type="password" autocomplete="off" required>
            <button type="submit">{{ __('Postfach öffnen') }}</button>
        </form>

        <p class="wb-hint">{{ __('Ein verlorenes Geheimnis kann nicht wiederhergestellt werden.') }}</p>
    </section>
@endsection

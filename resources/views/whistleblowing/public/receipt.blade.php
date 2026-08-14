{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : receipt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('whistleblowing.public.layout')

@section('title', __('Meldung eingegangen'))

@section('content')
    <section class="wb-card wb-success">
        <h2>{{ __('Ihre Meldung ist eingegangen.') }}</h2>
        <p>{{ __('Bewahren Sie die folgenden Zugangsdaten sicher auf. Sie werden nur EINMAL angezeigt und können NICHT wiederhergestellt werden. Mit ihnen rufen Sie das geschützte Postfach auf und kommunizieren – auch anonym – mit der Meldestelle.') }}</p>

        <dl class="wb-credentials">
            <dt>{{ __('Fallnummer') }}</dt>
            <dd><code>{{ $caseNumber }}</code></dd>
            <dt>{{ __('Geheimnis (Zugang zum Postfach)') }}</dt>
            <dd><code>{{ $secret }}</code></dd>
        </dl>

        <p class="wb-hint">{{ __('Die Fallnummer dient nur als Referenz. Der Postfachzugang erfolgt ausschließlich über das Geheimnis.') }}</p>
    </section>
@endsection

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

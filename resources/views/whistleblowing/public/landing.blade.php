@extends('whistleblowing.public.layout')

@section('title', __('Meldestelle'))

@section('content')
    <section class="wb-card">
        <h2>{{ __('Hinweisgeber-Meldeportal') }}</h2>
        <p>{{ __('Dies ist ein vertraulicher Meldekanal nach dem Hinweisgeberschutzgesetz (HinSchG). Jede Organisation nutzt einen eigenen, nicht öffentlichen Zugangslink.') }}</p>
        <p>{{ __('Den Zugangslink für Ihre Meldung erhalten Sie von Ihrem Unternehmen – zum Beispiel über das Intranet, einen Aushang oder die Unternehmenswebsite. Eine Suche nach Unternehmen gibt es hier bewusst nicht.') }}</p>
    </section>

    <section class="wb-card">
        <h2>{{ __('Bereits eine Meldung abgegeben?') }}</h2>
        <p>{{ __('Mit Ihrem Zugangsgeheimnis erreichen Sie Ihr anonymes Postfach – für Rückfragen der Meldestelle und den Stand Ihrer Meldung.') }}</p>
        <p><a href="{{ route('whistleblowing.mailbox.login') }}">{{ __('Zum Postfach') }}</a></p>
    </section>
@endsection

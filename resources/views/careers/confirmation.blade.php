{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : confirmation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('careers.layout', ['embed' => false])

@section('title', __('Bewerbung eingegangen'))

@section('content')
    <div class="card">
        <h2>{{ __('Vielen Dank für Ihre Bewerbung') }}</h2>
        <p>{{ __('Ihre Bewerbung ist bei uns eingegangen. Wir melden uns nach Prüfung der Unterlagen.') }}</p>
        @if($contactEmail)
            <p class="muted">{{ __('Rückfragen') }}: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
        @endif
        <p><a href="{{ route('careers.index', ['org' => $organization->slug]) }}">&larr; {{ __('Weitere Stellen ansehen') }}</a></p>
    </div>
@endsection

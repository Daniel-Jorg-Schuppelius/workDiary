{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('careers.layout', ['embed' => false])

@section('title', $content['title'] ?? __('Stelle'))

@section('content')
    <p class="meta"><a href="{{ route('careers.index', ['org' => $organization->slug]) }}">&larr; {{ __('Alle Stellen') }}</a></p>
    @include('careers._detail')
@endsection

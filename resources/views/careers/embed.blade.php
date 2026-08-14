{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : embed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('careers.layout', ['embed' => true])

@section('title', $content['title'] ?? __('Stelle'))

@section('content')
    @include('careers._detail')
@endsection

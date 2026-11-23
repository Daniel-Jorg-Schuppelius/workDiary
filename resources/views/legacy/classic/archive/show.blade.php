{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archivierter Eintrag, nur lesen. Erwartet: $entry --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.archive.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1 aria-label="{{ __('legacy_classic.archive.title') }}">{{ __('legacy_classic.archive.heading') }}</h1>
    @include('legacy.classic.diary._details', ['entry' => $entry, 'employee' => $entry->mitarbeiter?->uname, 'heading' => false])
    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.archive.week') }}">{{ __('legacy_classic.archive.back') }}</a></p>
@endsection

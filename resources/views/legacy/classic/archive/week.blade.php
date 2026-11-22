{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : week.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archiv wie im Altportal (archiv.php): Chefsicht der archivierten Wochen, nur lesen. --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_classic.archive.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1 aria-label="{{ __('legacy_classic.archive.title') }}">{{ __('legacy_classic.archive.heading') }}</h1>
    @include('legacy.classic.diary._week_select', ['route' => 'legacy.archive.week'])
    @include('legacy.classic.diary._chef_grid', [
        'route' => 'legacy.archive.week',
        'showRoute' => 'legacy.archive.show',
        'createLinks' => false,
    ])
    @include('legacy.classic.diary._legend')
@endsection

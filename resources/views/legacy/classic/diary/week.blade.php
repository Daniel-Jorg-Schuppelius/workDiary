{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : week.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tagebuch wie im Altportal (show.php): Chefsicht für alle Mitarbeiter, sonst die eigene Woche. --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.diary_title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    @include('legacy.classic.diary._week_select', ['route' => 'legacy.diary.week'])

    @if ($users->isEmpty())
        <p class="kp-alert">{{ __('legacy_classic.no_account') }}</p>
    @elseif ($canViewAll)
        @include('legacy.classic.diary._chef_grid', [
            'route' => 'legacy.diary.week',
            'showRoute' => 'legacy.diary.show',
            'createLinks' => $isAdmin && (bool) config('app.legacy_write_enabled'),
        ])
    @else
        @include('legacy.classic.diary._employee_grid', ['legacyUser' => $users->first()])
    @endif
    @include('legacy.classic.diary._legend')
@endsection

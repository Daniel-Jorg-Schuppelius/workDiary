{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : notdienst.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Notdienstplan wie im Altportal (callcenter/bereitschaft.php): gestern bis in fünf Tagen, heute gelb. Erwartet: $notdienstByDay, $callcenterUser --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_classic.callcenter.title'))
@section('bodyClass', 'tb')

@section('content')
    <h1>{{ __('legacy_classic.callcenter.title') }}</h1>
    @if (filled(config('app.legacy_callcenter_subtitle')))
        <p>{{ config('app.legacy_callcenter_subtitle') }}</p>
    @endif
    @include('legacy.classic._flash')
    @if ($callcenterUser !== '')
        <form method="POST" action="{{ route('legacy.callcenter.logout') }}" class="kp-msg">
            @csrf
            <button type="submit" class="kp-linkbutton" title="{{ __('legacy_classic.menu.logout_title') }}">&nbsp;{{ __('legacy_classic.menu.logout') }}&nbsp;</button>
        </form>
    @else
        <p><a href="{{ route('home') }}">{{ __('legacy_classic.menu.app', ['app' => config('app.name', 'WorkDiary')]) }}</a></p>
    @endif

    @php $locale = app()->getLocale(); @endphp
    <div class="kp-scroll">
        <table>
            <tr>
                @foreach ($notdienstByDay as $day)
                    <th class="{{ $day['isToday'] ? 'tb-today' : '' }}" @if ($day['holidayName']) title="{{ $day['holidayName'] }}" @endif>
                        {{ $day['date']->copy()->locale($locale)->isoFormat('dddd') }}<br>{{ $day['date']->format(\App\Support\Formats::date()) }}
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach ($notdienstByDay as $day)
                    <th class="{{ $day['isToday'] ? 'tb-today' : '' }}">{{ $day['user'] ?? '' }}</th>
                @endforeach
            </tr>
        </table>
    </div>
@endsection

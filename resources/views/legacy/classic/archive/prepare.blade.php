{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : prepare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Löschen von Aufträgen" wie im Altportal (delauftr.php): ältere Daten oder alles eines Mitarbeiters ins Archiv. Erwartet: $users, $writeEnabled --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.archiving.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.archiving.title') }}</h1>

    @if (! $writeEnabled)
        <p class="kp-alert kp-alert--error" role="alert">{{ __('legacy_classic.read_only') }}</p>
    @else
        <p>{{ __('legacy_classic.archiving.older_hint') }}</p>
        <p class="kp-legend">{{ __('legacy_classic.archiving.archive_hint') }}</p>
        <form method="POST" action="{{ route('legacy.archive.run') }}">
            @csrf
            <table class="kp-msg">
                @foreach ([3, 6, 9, 12] as $months)
                    <tr>
                        <td><label for="tb-months-{{ $months }}">{{ $months === 12 ? __('legacy_classic.archiving.one_year') : __('legacy_classic.archiving.months', ['count' => $months]) }}</label></td>
                        <td><input class="rad" type="radio" id="tb-months-{{ $months }}" name="months" value="{{ $months }}" @checked($months === 12)></td>
                    </tr>
                @endforeach
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_classic.action.delete') }}</button></td></tr>
            </table>
        </form>

        <p>{{ __('legacy_classic.archiving.all_of_employee') }}</p>
        <form method="POST" action="{{ route('legacy.archive.run') }}">
            @csrf
            <input type="hidden" name="months" value="0">
            <table class="kp-msg">
                <tr>
                    <td><label for="tb-archive-user">{{ __('legacy_classic.label.employee') }}</label></td>
                    <td>
                        <select id="tb-archive-user" name="user">
                            <option value="">{{ __('legacy_classic.nobody') }}</option>
                            @foreach ($users as $legacyUser)
                                <option value="{{ \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $legacyUser->id) }}">{{ $legacyUser->uname }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><td>&nbsp;</td><td><button type="submit">{{ __('legacy_classic.action.delete') }}</button></td></tr>
            </table>
        </form>
    @endif
@endsection

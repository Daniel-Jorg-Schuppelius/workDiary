{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : prepare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archivierung als eigener Menüpunkt „Löschen" wie im Altportal (admin/delauftr.php). Erwartet: $cutoff, $eligibleCount, $writeEnabled --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_support.classic.archiving.title'))

@section('content')
    @include('legacy.classic.support._office_menu')
    <h1>{{ __('legacy_support.classic.archiving.title') }}</h1>
    <h2>{{ __('legacy_support.classic.archiving.subtitle') }}</h2>
    @include('legacy.classic._flash')

    @if ($writeEnabled)
        <form method="POST" action="{{ route('legacy.support.archive.run') }}">
            @csrf
            <p>
                <label for="kp-before">{{ __('legacy_support.classic.archiving.text') }}</label>
                <input id="kp-before" type="date" name="before" value="{{ old('before', $cutoff->format('Y-m-d')) }}" required>
            </p>
            <p>{{ trans_choice('legacy_support.archive.eligible', $eligibleCount, ['count' => $eligibleCount, 'date' => $cutoff->fdate()]) }}</p>
            <p><button type="submit">{{ __('legacy_support.classic.archiving.run') }}</button></p>
        </form>
    @else
        <p class="kp-alert">{{ __('legacy_support.read_only') }}</p>
    @endif
@endsection

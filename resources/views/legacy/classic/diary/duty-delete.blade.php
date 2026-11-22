{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duty-delete.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachfrage vor dem Löschen eines Dienstes wie im Altportal. Erwartet: $item, $kind --}}
@php
    $tab = $kind === 'oncall' ? 'bereitschaft' : 'notdienst';
@endphp
@extends('legacy.classic.layout')
@section('title', __('legacy_classic.duty.' . $kind . '.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.duty.' . $kind . '.title') }}</h1>
    <p>{{ __('legacy_classic.duty.' . $kind . '.confirm_delete', ['name' => $item->mitarbeiter?->uname ?? '']) }}</p>
    <form method="POST" action="{{ route('legacy.' . $kind . '.destroy', $item) }}" class="kp-msg">
        @csrf
        @method('DELETE')
        &raquo; <button type="submit" class="kp-linkbutton">{{ __('legacy_classic.yes') }}</button> &laquo;
        &nbsp; &nbsp; &nbsp;
        &raquo; <a href="{{ route('legacy.diary.index', ['tab' => $tab]) }}">{{ __('legacy_classic.no') }}</a> &laquo;
    </form>
@endsection

{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Korrekturantrag #:id', ['id' => $request->id]))
@section('nav-title', __('Korrekturantrag #:id', ['id' => $request->id]))

@section('content')
    <x-index-page :subtitle="$request->user?->name . ' · ' . optional($request->scope_date)->fdate()"
                  back-route="corrections.index" :back-label="__('Zurück')">

        @include('time-approval.correction._summary')

        <div class="flex gap-2 mt-4">
            @can('submit', $request)
                <form method="POST" action="{{ route('corrections.submit', $request) }}">
                    @csrf
                    <x-button tone="primary" type="submit" icon="send">{{ __('Einreichen') }}</x-button>
                </form>
            @endcan
            @can('withdraw', $request)
                <form method="POST" action="{{ route('corrections.withdraw', $request) }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Antrag wirklich zurückziehen?') }}"
                      data-confirm-icon="undo"
                      data-confirm-tone="warning"
                      data-confirm-label="{{ __('Zurückziehen') }}">
                    @csrf
                    <x-button tone="ghost" type="submit" icon="undo">{{ __('Zurückziehen') }}</x-button>
                </form>
            @endcan
        </div>
    </x-index-page>
@endsection

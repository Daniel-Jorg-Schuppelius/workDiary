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
                  back-route="admin.corrections.index" :back-label="__('Zurück')">

        @include('time-approval.correction._summary')

        <div class="flex flex-wrap gap-2 mt-4">
            @can('approve', $request)
                <form method="POST" action="{{ route('admin.corrections.approve', $request) }}" class="flex gap-2 items-end">
                    @csrf
                    <input aria-label="{{ __('Optionaler Vermerk') }}" type="text" name="note" class="input input-sm input-bordered"
                           placeholder="{{ __('Optionaler Vermerk') }}" maxlength="500" />
                    <x-button tone="success" size="sm" type="submit" icon="check">{{ __('Genehmigen') }}</x-button>
                </form>
            @endcan
            @can('reject', $request)
                <form method="POST" action="{{ route('admin.corrections.reject', $request) }}" class="flex gap-2 items-end">
                    @csrf
                    <input aria-label="{{ __('Begründung ≥ 20 Zeichen') }}" type="text" name="note" class="input input-sm input-bordered w-72"
                           placeholder="{{ __('Begründung ≥ 20 Zeichen') }}" minlength="20" maxlength="2000" required />
                    <x-button tone="error" size="sm" type="submit" icon="close">{{ __('Ablehnen') }}</x-button>
                </form>
            @endcan
            @can('apply', $request)
                <x-action-form :action="route('admin.corrections.apply', $request)"
                      :confirm="__('Antrag jetzt anwenden?')"
                      confirm-icon="play_arrow"
                      confirm-tone="primary"
                      :confirm-label="__('Anwenden')">
                    <x-button tone="primary" size="sm" type="submit" icon="play_arrow">{{ __('Anwenden') }}</x-button>
                </x-action-form>
            @endcan
        </div>
    </x-index-page>
@endsection

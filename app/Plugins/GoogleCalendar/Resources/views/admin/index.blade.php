{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('google_calendar.title'))
@section('nav-title', __('google_calendar.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Status + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('google_calendar.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('google_calendar.health.badge_ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('google_calendar.health.badge_failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('google_calendar.health.badge_inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-base-content/60">{{ __('google_calendar.intro') }}</p>

            @unless ($configured)
                <div class="alert alert-warning text-sm">{{ __('google_calendar.not_configured_hint') }}</div>
            @endunless

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.google-calendar.publish') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('google_calendar.action.publish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.google-calendar.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('google_calendar.action.disconnect') }}</button>
                    </form>
                </div>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.google-calendar.oauth.start') }}" data-oauth-popup>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('google_calendar.action.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Ziel-Kalender --}}
        @if ($connection && $connection->isActive())
            <form method="POST" action="{{ route('admin.google-calendar.calendar.store') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('google_calendar.calendar.heading') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('google_calendar.calendar.help') }}</p>

                <label class="form-control max-w-md">
                    <span class="label-text">{{ __('google_calendar.calendar.target') }}</span>
                    <select name="calendar_id" class="select select-bordered select-sm">
                        <option value="">{{ __('google_calendar.calendar.default') }}</option>
                        @foreach ($calendars as $calendar)
                            <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- MVP-610a: Der Rückimport ändert Daten und läuft deshalb nur auf Zuruf. --}}
                <label class="flex items-center gap-2 text-sm" title="{{ __('google_calendar.calendar.two_way_hint') }}">
                    <input type="hidden" name="two_way" value="0">
                    <input type="checkbox" name="two_way" value="1" class="checkbox checkbox-sm"
                           @checked(old('two_way', $connection->two_way))>
                    {{ __('google_calendar.calendar.two_way') }}
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('google_calendar.action.save') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection

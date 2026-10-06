{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('google_calendar::google_calendar.title'))
@section('nav-title', __('google_calendar::google_calendar.title'))

@section('content')
<x-index-page :subtitle="__('google_calendar::google_calendar.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            @if (($health['ok'] ?? false))
                <x-status-badge tone="success">{{ __('google_calendar::google_calendar.health.badge_ok') }}</x-status-badge>
            @else
                <x-status-badge tone="error">{{ __('google_calendar::google_calendar.health.badge_failing') }}</x-status-badge>
            @endif
        @elseif ($connection)
            <x-status-badge>{{ __('google_calendar::google_calendar.health.badge_inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @unless ($configured)
            <div role="alert" class="alert alert-warning text-sm">{{ __('google_calendar::google_calendar.not_configured_hint') }}</div>
        @endunless

        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.google-calendar.publish') }}">
                    @csrf
                    <x-button type="submit">{{ __('google_calendar::google_calendar.action.publish') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.google-calendar.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('google_calendar::google_calendar.action.disconnect') }}</x-button>
                </form>
            </div>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.google-calendar.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('google_calendar::google_calendar.action.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Ziel-Kalender --}}
    @if ($connection && $connection->isActive())
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.google-calendar.calendar.store') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('google_calendar::google_calendar.calendar.heading') }}</h2>
            <p class="text-sm text-muted">{{ __('google_calendar::google_calendar.calendar.help') }}</p>

            <label class="form-control max-w-md">
                <span class="label-text">{{ __('google_calendar::google_calendar.calendar.target') }}</span>
                <select name="calendar_id" class="select select-bordered select-sm">
                    <option value="">{{ __('google_calendar::google_calendar.calendar.default') }}</option>
                    @foreach ($calendars as $calendar)
                        <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}</option>
                    @endforeach
                </select>
            </label>

            {{-- MVP-610a: Der Rückimport ändert Daten und läuft deshalb nur auf Zuruf. --}}
            <label class="flex items-center gap-2 text-sm" title="{{ __('google_calendar::google_calendar.calendar.two_way_hint') }}">
                <input type="hidden" name="two_way" value="0">
                <input type="checkbox" name="two_way" value="1" class="checkbox checkbox-sm"
                       @checked(old('two_way', $connection->two_way))>
                {{ __('google_calendar::google_calendar.calendar.two_way') }}
            </label>

            <div class="flex justify-end">
                <x-button type="submit">{{ __('google_calendar::google_calendar.action.save') }}</x-button>
            </div>
        </x-card>
    @endif
</x-index-page>
@endsection

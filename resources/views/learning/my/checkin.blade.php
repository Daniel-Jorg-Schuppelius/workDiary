{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : checkin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  QR-Check-in (Feature 149, MVP-741). Bestätigt wird per POST — ein
  Vorschau-Scanner darf die Anwesenheit nicht setzen.
--}}
@extends('layouts.app')
@section('title', __('learning.title.checkin'))
@section('nav-title', __('learning.title.checkin'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$unit->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.my.show', $enrollment->sqid)"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <h3 class="text-sm font-semibold">{{ $event->title }}</h3>
        <p class="mt-1 text-xs text-muted">
            {{ $event->started_at?->translatedFormat('d.m.Y H:i') }}
            @if ($event->ended_at) – {{ $event->ended_at->translatedFormat('H:i') }} @endif
        </p>

        @if ($open)
            <p class="mt-3 text-sm text-base-content/80">{{ __('learning.help.checkin_self') }}</p>
            <form method="POST" action="{{ route('learning.checkin.store', $unit->sqid) }}" class="mt-3">
                @csrf
                <x-icon-btn icon="how_to_reg" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.checkin') }}</x-icon-btn>
            </form>
        @else
            {{-- Kein Dauerticket: außerhalb des Fensters trägt nur die
                 Kursleitung nach, die die Liste gesehen hat. --}}
            <div class="alert alert-warning mt-3" role="status">
                <x-icon name="schedule" />
                <span>{{ __('learning.errors.checkin_closed') }}
                    ({{ $window[0]->translatedFormat('d.m.Y H:i') }} – {{ $window[1]->translatedFormat('d.m.Y H:i') }})</span>
            </div>
        @endif
    </x-card>
</x-page-shell>
@endsection

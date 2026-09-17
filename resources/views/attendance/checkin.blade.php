{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : checkin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- QR-/NFC-Check-in (MVP-800). Variablen: $checkpoint, $open (Attendance|null) --}}
@extends('layouts.app')
@section('title', __('attendance.checkin.title'))
@section('nav-title', __('attendance.checkin.title'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('attendance.checkin.subtitle')" />
    </x-slot:toolbar>

    <div class="mx-auto w-full max-w-md">
        <x-card>
            <div class="mb-4 text-center">
                <div class="text-xs uppercase tracking-wider text-muted">{{ $checkpoint->kind->label() }}</div>
                <h2 class="text-2xl font-semibold">{{ $checkpoint->name }}</h2>
                @if ($checkpoint->vehicle)
                    <div class="text-sm text-muted">{{ $checkpoint->vehicle->displayName() }}</div>
                @elseif ($checkpoint->site)
                    <div class="text-sm text-muted">{{ $checkpoint->site->name }}</div>
                @endif
            </div>

            <p class="mb-4 text-center text-sm">
                @if ($open)
                    {{ __('attendance.checkin.state.in', ['time' => $open->started_at->ftime()]) }}
                @else
                    {{ __('attendance.checkin.state.out') }}
                @endif
            </p>

            <form method="POST" action="{{ route('checkin.stamp', $checkpoint->token) }}"
                  @if ($checkpoint->requiresLocation()) data-checkin-requires-location @endif>
                @csrf
                <input type="hidden" name="action" value="{{ $open ? 'out' : 'in' }}">
                <input type="hidden" name="latitude" data-checkin-latitude>
                <input type="hidden" name="longitude" data-checkin-longitude>
                <x-validation-errors first />
                <button type="submit" class="btn btn-lg w-full {{ $open ? 'btn-warning' : 'btn-primary' }}">
                    {{ $open ? __('attendance.checkin.action.out') : __('attendance.checkin.action.in') }}
                </button>
                @if ($checkpoint->requiresLocation())
                    <p class="mt-3 text-center text-xs text-muted">{{ __('attendance.checkin.location_hint', ['radius' => $checkpoint->radius_m]) }}</p>
                    <p class="mt-2 hidden text-center text-sm text-error" role="alert" data-checkin-location-error>{{ __('attendance.checkin.error.location_denied') }}</p>
                @endif
            </form>
        </x-card>
    </div>
</x-page-shell>
@endsection

@if ($checkpoint->requiresLocation())
    @push('scripts')
        @vite('resources/js/checkin.js')
    @endpush
@endif

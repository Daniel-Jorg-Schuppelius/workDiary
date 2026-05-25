{{--
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : index.blade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kalender'))
@section('nav-title', __('Kalender'))

@push('scripts')
    @vite('resources/js/calendar.js')
@endpush

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Bereitschaft, Notdienst und Tagebucheinträge im Überblick.')">
            <x-slot:actions>
                <x-icon-btn icon="view_week" tone="secondary" size="sm"
                            :href="route('week.index')"
                            show-label>{{ __('Wochenansicht') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div
            id="calendar"
            data-events-url="{{ route('calendar.events') }}"
            data-locale="{{ app()->getLocale() }}"
            data-view="timeGridWeek"
        ></div>
    </x-card>
</x-page-shell>
@endsection

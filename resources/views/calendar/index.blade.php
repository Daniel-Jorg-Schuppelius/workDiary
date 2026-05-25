{{--
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : index.blade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@push('scripts')
    @vite('resources/js/calendar.js')
@endpush

@section('content')
<x-page-shell gap="gap-6">
    <x-page-toolbar :title="__('Kalender')" :subtitle="__('Bereitschaft, Notdienst und Tagebucheinträge im Überblick.')">
        <x-slot:actions>
            <a href="{{ route('week.index') }}" class="btn btn-secondary">
                <x-icon name="view_week" class="mr-1" /> {{ __('Wochenansicht') }}
            </a>
        </x-slot:actions>
    </x-page-toolbar>

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

@extends('layouts.app')
@section('title', __('Veranstaltungs-Kalender'))
@section('nav-title', __('Veranstaltungs-Kalender'))
{{-- Volle Viewport-Höhe wie Schichtplan: Wrapper bekommt fixe Höhe, Main
     ist Flex-Container — damit das Kalender-Grid die restliche Höhe nutzt. --}}
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use Carbon\CarbonImmutable;

    /** @var CarbonImmutable $monthStart */
    /** @var CarbonImmutable $monthEnd */
    /** @var \Illuminate\Support\Collection $eventsByDay */ // key = Y-m-d
    /** @var list<array{key:string,year:int,month:int,label:string,shortLabel:string}> $months */
    /** @var string $activeMonthKey */
@endphp

@section('content')
    <x-page-shell overflow="clip">
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <x-icon-btn icon="list" tone="ghost" size="sm" :href="route('events.index')" show-label>{{ __('Liste') }}</x-icon-btn>
                    @can('create', App\Models\Event::class)
                        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('events.create').'?dialog=1'" show-label>
                            {{ __('Neue Veranstaltung') }}
                        </x-icon-btn>
                    @endcan
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        {{-- Monats-Tabs (nur wenn globaler Header-Zeitraum > 1 Monat umfasst) --}}
        @if (count($months) > 1)
            <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
                @foreach ($months as $m)
                    <a role="tab"
                       href="{{ route('events.calendar', ['activeMonth' => $m['key']]) }}"
                       class="tab whitespace-nowrap gap-1.5 {{ $m['key'] === $activeMonthKey ? 'tab-active' : '' }}">
                        <span class="font-semibold">{{ $m['shortLabel'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <x-month-calendar
            :month="$monthStart"
            :items-by-day="$eventsByDay"
            :holidays="$holidays"
            item-view="events.partials._calendar_cell"
            full-height />
    </x-page-shell>
@endsection

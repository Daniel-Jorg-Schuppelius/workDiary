{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : absence-calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Urlaubsplan-Jahresübersicht (MVP-520): je Mitarbeiter ein Jahresbalken mit
  Fehlzeiträumen; Datenschutz-Filter neutralisiert fremde Fehlgründe.
--}}

@extends('layouts.app')
@section('title', __('Urlaubsplan (Jahresübersicht)'))
@section('nav-title', __('Urlaubsplan'))

@section('content')
@php
    $toneBg = [
        'warning' => 'bg-warning/80',
        'error' => 'bg-error/70',
        'info' => 'bg-info/70',
        'neutral' => 'bg-base-content/30',
        // MVP-536: Vorbehalts-Eintragung — bewusst blass (schraffiert wirkend).
        'ghost' => 'bg-warning/30',
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Ganzjahresübersicht der Fehlzeiträume — Kollisionen auf einen Blick.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="request()->fullUrlWithQuery(['export' => 'csv'])" show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="request()->fullUrlWithQuery(['export' => 'xlsx'])" show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="request()->fullUrlWithQuery(['export' => 'pdf'])" show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.absence-calendar')" :reset="route('reports.absence-calendar')">
        <x-filter-field :label="__('Jahr')" for="ac-year">
            <input id="ac-year" type="number" name="year" min="2000" max="2100"
                   value="{{ $year }}" class="input input-sm input-bordered w-24" data-autosubmit>
        </x-filter-field>
        @if ($isAdmin && $teams->isNotEmpty())
            <x-filter-field :label="__('Team')" for="ac-team">
                <select id="ac-team" name="team" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('Alle Teams') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->sqid }}" @selected($teamFilter === $team->sqid)>{{ $team->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        @if ($isAdmin)
            <label class="label cursor-pointer gap-2 order-40">
                <input type="checkbox" name="anon" value="1" class="toggle toggle-sm" data-autosubmit
                       @checked($anonymize)>
                <span class="label-text">{{ __('Fehlgründe anonymisieren') }}</span>
            </label>
        @endif
    </x-filter-bar>

    <x-card>
        {{-- Legende --}}
        <div class="flex flex-wrap items-center gap-4 mb-3 text-sm">
            @foreach ($legend as $label => $tone)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm {{ $toneBg[$tone] }}"></span>{{ $label }}
                </span>
            @endforeach
        </div>

        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">event_busy</span>'
                           :title="__('Keine Mitarbeiter im gewählten Bereich.')" />
        @else
            <div class="overflow-x-auto">
                <div class="min-w-[56rem]">
                    {{-- Monatsskala --}}
                    <div class="flex">
                        <div class="w-48 shrink-0"></div>
                        <div class="relative flex-1 h-6 border-b border-base-300">
                            @foreach ($monthStarts as $month)
                                <span class="absolute top-0 text-xs text-base-content/60 border-l border-base-300 pl-1"
                                      style="left: {{ $month['left'] }}%">{{ $month['label'] }}</span>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($rows as $row)
                        <div class="flex items-center border-b border-base-200 hover:bg-base-200/40">
                            <div class="w-48 shrink-0 py-1.5 pr-2 truncate">
                                <a class="link link-hover" href="{{ route('reports.absence-calendar', ['year' => $year, 'user' => $row['sqid']]) }}">
                                    {{ $row['user']->name }}
                                </a>
                            </div>
                            <div class="relative flex-1 h-6">
                                @foreach ($monthStarts as $month)
                                    <span class="absolute inset-y-0 border-l border-base-200" style="left: {{ $month['left'] }}%"></span>
                                @endforeach
                                @foreach ($row['bars'] as $bar)
                                    <span class="absolute inset-y-1 rounded-sm {{ $toneBg[$bar['tone']] }}"
                                          style="left: {{ $bar['left'] }}%; width: {{ $bar['width'] }}%"
                                          title="{{ $bar['label'] }}: {{ $bar['from'] }} – {{ $bar['to'] }}"></span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-card>
</x-page-shell>
@endsection

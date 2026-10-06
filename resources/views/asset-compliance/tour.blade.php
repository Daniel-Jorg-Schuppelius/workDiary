{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tour.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfertouren (MVP-918). Erwartet: $users, $inspector, $until, $date, $schedules, $available --}}
@extends('layouts.app')

@section('title', __('inspection_tour.title'))
@section('nav-title', __('inspection_tour.title'))

@section('content')
    <x-index-page :subtitle="__('inspection_tour.subtitle')"
                  back-route="asset-compliance.schedules.index" :back-label="__('Prüfkalender')">

        <x-filter-bar :action="route('asset-compliance.tours.index')" :reset="route('asset-compliance.tours.index')">
            <select name="inspector" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('inspection_tour.inspector') }}">
                @foreach ($users as $user)
                    <option value="{{ $user->sqid }}" @selected($inspector?->id === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <input type="date" name="until" value="{{ $until->format('Y-m-d') }}" class="input input-sm input-bordered w-40 shrink-0"
                   aria-label="{{ __('inspection_tour.due_until') }}" title="{{ __('inspection_tour.due_until') }}">
        </x-filter-bar>

        @unless ($available)
            <div role="status" class="alert alert-info">
                <x-icon name="info" />
                <span>{{ __('inspection_tour.unavailable') }}</span>
            </div>
        @endunless

        <form method="POST" action="{{ route('asset-compliance.tours.store') }}" class="flex flex-col gap-3">
            @csrf
            <input type="hidden" name="inspector_user_id" value="{{ $inspector?->sqid }}">
            <input type="hidden" name="until" value="{{ $until->format('Y-m-d') }}">

            <x-card padding="p-0">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th class="w-8"><span class="sr-only">{{ __('inspection_tour.select') }}</span></th>
                            <th>{{ __('Fällig') }}</th>
                            <th>{{ __('Asset') }}</th>
                            <th>{{ __('Prüfprofil') }}</th>
                            <th>{{ __('inspection_tour.location') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td><input type="checkbox" name="schedule_ids[]" value="{{ $schedule->sqid }}" class="checkbox checkbox-sm" checked
                                       aria-label="{{ __('inspection_tour.select') }}: {{ $schedule->asset?->name }}"></td>
                            <td class="whitespace-nowrap">{{ $schedule->due_on->fdate() }}</td>
                            <td>{{ $schedule->asset?->name ?? '—' }}</td>
                            <td>{{ $schedule->assignment?->profile?->name ?? '—' }}</td>
                            <td>
                                {{ $schedule->asset?->location_text ?? '—' }}
                                @if ($schedule->asset?->location_lat === null)
                                    <x-status-badge tone="warning" outline title="{{ __('inspection_tour.no_coordinates_hint') }}">{{ __('inspection_tour.no_coordinates') }}</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="5" :title="__('inspection_tour.none')" />
                    @endforelse
                </x-table>
            </x-card>

            @if ($available && $schedules->isNotEmpty())
                <div class="flex flex-wrap items-end justify-end gap-2">
                    <x-input-field name="date" type="date" :label="__('inspection_tour.date')" :value="old('date', $date->format('Y-m-d'))" required />
                    <x-button type="submit" icon="route">{{ __('inspection_tour.plan') }}</x-button>
                </div>
            @endif
        </form>
    </x-index-page>
@endsection

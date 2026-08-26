{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Wächterrundgänge'))
@section('nav-title', __('Rundgänge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kontrollpunkte mit Soll-Fenstern und Scan-Nachweis — der belastbare Beleg gegenüber Auftraggebern.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('patrols.create')" show-label>{{ __('Route anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($openRuns->isNotEmpty())
        <div class="alert alert-info text-sm">
            <x-icon name="directions_walk" />
            <div>
                @foreach ($openRuns as $run)
                    <a class="link" href="{{ route('patrols.runs.show', $run) }}">{{ $run->route?->name }}</a>
                    ({{ $run->starter?->name }}, {{ __('seit :time', ['time' => $run->started_at->format('H:i')]) }})@if(!$loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif

    @if ($routes->isEmpty())
        <x-empty-state framed icon="route"
                       :title="__('Noch keine Rundgangs-Routen.')"
                       :message="__('Eine Route ist eine geordnete Liste von Kontrollpunkten mit Soll-Zeiten — der Scan belegt Punkt und Zeit.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Objekt') }}</th>
                    <th>{{ __('Kontrollpunkte') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($routes as $route)
                <tr class="hover">
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('patrols.show', $route) }}">{{ $route->name }}</a>
                        @unless ($route->active)<span class="badge badge-ghost badge-xs align-middle">{{ __('inaktiv') }}</span>@endunless
                    </td>
                    <td class="text-sm">{{ $route->site?->name ?? '—' }}</td>
                    <td class="text-sm tabular-nums">{{ $route->checkpoints_count }}</td>
                    <td class="text-right">
                        <x-action-form :action="route('patrols.start', $route)">
                            <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Starten') }}</x-icon-btn>
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$routes" standing />
    @endif
</x-index-page>
@endsection

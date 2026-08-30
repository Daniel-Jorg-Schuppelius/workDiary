{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Route: :name', ['name' => $route->name]))
@section('nav-title', __('Rundgangs-Route'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $route->name }}</span>
                @if ($route->site)<span class="text-sm text-muted">· {{ $route->site->name }}</span>@endif
            </div>
            <x-slot:actions>
                <x-action-form :action="route('patrols.start', $route)">
                    <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Rundgang starten') }}</x-icon-btn>
                </x-action-form>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('patrols.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('patrol_token_once'))
        {{-- Der Klartext-Token erscheint genau EINMAL — er gehört auf den Tag,
             nicht in die Datenbank (nur der Hash bleibt). --}}
        <div class="alert alert-warning text-sm" role="alert">
            <x-icon name="key" />
            <div>
                <p class="font-semibold">{{ __('Token — nur jetzt sichtbar (auf den Tag drucken/schreiben):') }}</p>
                <code class="font-mono text-base">{{ session('patrol_token_once') }}</code>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Kontrollpunkte')">
                @if ($route->checkpoints->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Kontrollpunkte — unten hinzufügen.') }}</p>
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Kontrollpunkt') }}</th>
                                <th>{{ __('Soll (ab Start)') }}</th>
                                <th>{{ __('Toleranz') }}</th>
                                <th>{{ __('Token') }}</th>
                                @if ($canManage)<th></th>@endif
                            </tr>
                        </x-slot:head>
                        @foreach ($route->checkpoints as $checkpoint)
                            <tr>
                                <td class="tabular-nums">{{ $checkpoint->position }}</td>
                                <td>{{ $checkpoint->label }}</td>
                                <td class="tabular-nums">+{{ $checkpoint->expected_offset_minutes }} min</td>
                                <td class="tabular-nums">± {{ $checkpoint->tolerance_minutes }} min</td>
                                <td class="font-mono text-xs text-muted">…{{ $checkpoint->token_suffix }}</td>
                                @if ($canManage)
                                    <td class="text-right">
                                        <x-action-form :action="route('patrols.checkpoints.reissue', [$route, $checkpoint])"
                                                       :confirm="__('Neuen Token ausgeben? Der alte Tag wird damit sofort wertlos.')"
                                                       :confirm-label="__('Neu ausgeben')">
                                            <x-icon-btn icon="refresh" size="sm" type="submit" :title="__('Token neu ausgeben (Tag verloren)')" />
                                        </x-action-form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </x-table>
                @endif

                @if ($canManage)
                    <form method="POST" action="{{ route('patrols.checkpoints.add', $route) }}" class="mt-4 grid gap-2 sm:grid-cols-4">
                        @csrf
                        <input aria-label="{{ __('Kontrollpunkt (Ort)') }}" type="text" name="label" required maxlength="160" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Kontrollpunkt (Ort)') }}">
                        <input type="number" name="expected_offset_minutes" required min="0" max="1440" value="0" class="input input-sm input-bordered" placeholder="{{ __('Soll ab Start (min)') }}" aria-label="{{ __('Soll ab Start (min)') }}">
                        <input type="number" name="tolerance_minutes" required min="0" max="240" value="10" class="input input-sm input-bordered" placeholder="{{ __('Toleranz (min)') }}" aria-label="{{ __('Toleranz (min)') }}">
                        <button type="submit" class="btn btn-primary btn-sm sm:col-span-4">{{ __('Kontrollpunkt hinzufügen') }}</button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Letzte Rundgänge')">
                @if ($runs->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Rundgänge.') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($runs as $run)
                            <li class="flex justify-between gap-2">
                                <a class="link link-hover min-w-0 truncate" href="{{ route('patrols.runs.show', $run) }}">
                                    {{ $run->started_at->format('d.m.Y H:i') }} · {{ $run->starter?->name ?? '—' }}
                                </a>
                                <span class="shrink-0 text-muted">{{ $run->scans_count }} {{ __('Scans') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

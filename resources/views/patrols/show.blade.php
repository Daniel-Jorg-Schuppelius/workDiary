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
        <x-page-toolbar back-route="patrols.index" :back-label="__('Zur Liste')">
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $route->name }}</span>
                @if ($route->site)<span class="text-sm text-muted">· {{ $route->site->name }}</span>@endif
                @unless ($route->active)<x-status-badge size="xs">{{ __('inaktiv') }}</x-status-badge>@endunless
                @if ($route->responsible)<span class="text-sm text-muted" title="{{ __('patrol.route.responsible') }}">· {{ $route->responsible->name }}</span>@endif
            </div>
            <x-slot:actions>
                @if ($route->active)
                    <x-action-form :action="route('patrols.start', $route)">
                        <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Rundgang starten') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('patrols.edit', $route)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    <x-action-form :action="route('patrols.toggle', $route)"
                                   :confirm="$route->active ? __('patrol.route.deactivate_confirm') : null">
                        <x-icon-btn :icon="$route->active ? 'pause' : 'play_arrow'" size="sm" type="submit" show-label>{{ $route->active ? __('Deaktivieren') : __('Aktivieren') }}</x-icon-btn>
                    </x-action-form>
                @endif
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
                    <x-empty-state icon="location_on" :title="__('Noch keine Kontrollpunkte — unten hinzufügen.')" compact />
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
                            <tr @class(['opacity-60' => $checkpoint->deactivated_at !== null])>
                                <td class="tabular-nums">{{ $checkpoint->position }}</td>
                                <td>
                                    {{ $checkpoint->label }}
                                    @if ($checkpoint->deactivated_at !== null)
                                        <x-status-badge size="xs" :title="$checkpoint->deactivated_at->orgTz()->fdatetime()">{{ __('patrol.checkpoint.inactive_badge') }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="tabular-nums">+{{ $checkpoint->expected_offset_minutes }} min</td>
                                <td class="tabular-nums">± {{ $checkpoint->tolerance_minutes }} min</td>
                                <td class="font-mono text-xs text-muted">…{{ $checkpoint->token_suffix }}</td>
                                @if ($canManage)
                                    <td class="text-right">
                                        @if ($checkpoint->deactivated_at === null)
                                            <div class="flex justify-end gap-1">
                                                <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                                            :href="route('patrols.checkpoints.edit', [$route, $checkpoint])" :label="__('patrol.checkpoint.edit_title')" />
                                                <x-action-form :action="route('patrols.checkpoints.reissue', [$route, $checkpoint])"
                                                               :confirm="__('Neuen Token ausgeben? Der alte Tag wird damit sofort wertlos.')"
                                                               :confirm-label="__('Neu ausgeben')">
                                                    <x-icon-btn icon="refresh" size="sm" type="submit" :title="__('Token neu ausgeben (Tag verloren)')" />
                                                </x-action-form>
                                                <x-action-form :action="route('patrols.checkpoints.deactivate', [$route, $checkpoint])"
                                                               :confirm="__('patrol.checkpoint.deactivate_confirm')"
                                                               :confirm-label="__('Deaktivieren')">
                                                    <x-icon-btn icon="block" size="sm" type="submit" :label="__('Deaktivieren')" />
                                                </x-action-form>
                                            </div>
                                        @endif
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
                        <x-button type="submit" class="sm:col-span-4">{{ __('Kontrollpunkt hinzufügen') }}</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Letzte Rundgänge')">
                @if ($runs->isEmpty())
                    <x-empty-state icon="directions_walk" :title="__('Noch keine Rundgänge.')" compact />
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($runs as $run)
                            <li class="flex justify-between gap-2">
                                <a class="link link-hover min-w-0 truncate" href="{{ route('patrols.runs.show', $run) }}">
                                    {{ $run->started_at->fdatetime() }} · {{ $run->starter?->name ?? '—' }}
                                </a>
                                <span class="shrink-0 text-muted">
                                    {{ $run->scans_count }} {{ __('Scans') }}
                                    <x-status-badge size="xs" :tone="$run->status === \App\Enums\Patrol\PatrolRunStatus::Aborted ? 'warning' : 'ghost'"
                                                    :title="$run->abort_reason">{{ $run->status->label() }}</x-status-badge>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

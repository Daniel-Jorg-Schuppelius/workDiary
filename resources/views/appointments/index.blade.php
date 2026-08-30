{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Terminanfragen'))
@section('nav-title', __('Terminanfragen'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">{{ __('Portal-Anfragen entscheiden — erst die Bestätigung erzeugt den Dispositions-Eintrag.') }}</div>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Offene Anfragen')">
                @if ($requests->isEmpty())
                    <p class="text-sm text-muted">{{ __('Keine offenen Terminanfragen.') }}</p>
                @else
                    <div class="space-y-2">
                        @foreach ($requests as $request)
                            <div class="rounded-lg border border-base-300 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div class="min-w-0">
                                        <span class="font-medium">{{ $request->start_at?->format('d.m.Y H:i') }}–{{ $request->end_at?->format('H:i') }}</span>
                                        · {{ $request->customer?->name ?? $request->invitee_name }}
                                        · {{ $request->service_label }}
                                    </div>
                                    @if ($canManage)
                                        <div class="flex shrink-0 gap-2">
                                            <x-action-form :action="route('appointments.confirm', $request)">
                                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                                            </x-action-form>
                                        </div>
                                    @endif
                                </div>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('appointments.decline', $request) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <input aria-label="{{ __('Ablehnungsgrund (geht an den Kunden)') }}" type="text" name="reason" required maxlength="500"
                                               class="input input-xs input-bordered w-full"
                                               placeholder="{{ __('Ablehnungsgrund (geht an den Kunden)') }}">
                                        <button type="submit" class="btn btn-ghost btn-xs shrink-0 text-error">{{ __('Ablehnen') }}</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Zuletzt entschieden')">
                @if ($decided->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch nichts entschieden.') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($decided as $request)
                            <li class="flex justify-between gap-2">
                                <span class="min-w-0 truncate">{{ $request->start_at?->format('d.m.Y H:i') }} · {{ $request->customer?->name ?? $request->invitee_name }}</span>
                                <span class="shrink-0 text-muted">{{ [
                                    'confirmed' => __('bestätigt'),
                                    'declined' => __('abgelehnt'),
                                    'canceled' => __('storniert'),
                                ][$request->status] ?? $request->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Buchbare Leistungsarten')">
                @if ($services->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Leistungsart buchbar — nichts ist automatisch buchbar.') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($services as $service)
                            <li class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate">{{ $service->title }} <span class="text-xs text-muted">({{ $service->duration_minutes }} min, {{ __('Vorlauf :h h', ['h' => $service->lead_time_hours]) }})</span></span>
                                @if ($canManage)
                                    <x-action-form :action="route('appointments.services.toggle', $service)">
                                        <x-icon-btn :icon="$service->active ? 'pause' : 'play_arrow'" size="sm" type="submit"
                                                    :title="$service->active ? __('Deaktivieren') : __('Aktivieren')" />
                                    </x-action-form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canManage)
                    <form method="POST" action="{{ route('appointments.services.store') }}" class="mt-4 grid gap-2">
                        @csrf
                        <input aria-label="{{ __('Leistungsart (z. B. Wartungstermin)') }}" type="text" name="title" required maxlength="160" class="input input-sm input-bordered" placeholder="{{ __('Leistungsart (z. B. Wartungstermin)') }}">
                        <input aria-label="{{ __('Beschreibung (im Portal sichtbar)') }}" type="text" name="description" maxlength="500" class="input input-sm input-bordered" placeholder="{{ __('Beschreibung (im Portal sichtbar)') }}">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="duration_minutes" required min="15" max="480" value="60" class="input input-sm input-bordered" aria-label="{{ __('Dauer (min)') }}" placeholder="{{ __('Dauer (min)') }}">
                            <input type="number" name="buffer_minutes" required min="0" max="120" value="15" class="input input-sm input-bordered" aria-label="{{ __('Puffer (min)') }}" placeholder="{{ __('Puffer (min)') }}">
                            <input type="number" name="lead_time_hours" required min="0" max="720" value="24" class="input input-sm input-bordered" aria-label="{{ __('Vorlauf (h)') }}" placeholder="{{ __('Vorlauf (h)') }}">
                            <input type="number" name="cancel_hours" required min="0" max="720" value="24" class="input input-sm input-bordered" aria-label="{{ __('Stornofrist (h)') }}" placeholder="{{ __('Stornofrist (h)') }}">
                        </div>
                        {{-- Nur Fenster von Kräften mit gültiger Qualifikation
                             werden angeboten — ein unfahrbarer Slot wäre ein
                             leeres Versprechen. --}}
                        <select name="qualification" class="select select-sm select-bordered" aria-label="{{ __('Benötigte Qualifikation') }}">
                            <option value="">{{ __('— keine Qualifikation nötig —') }}</option>
                            @foreach ($qualifications as $qualification)
                                <option value="{{ $qualification->sqid }}">{{ $qualification->name }}</option>
                            @endforeach
                        </select>
                        <select name="site" class="select select-sm select-bordered" aria-label="{{ __('Objekt / Standort') }}">
                            <option value="">{{ __('— kein Standortbezug —') }}</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->sqid }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Leistungsart anlegen') }}</button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection

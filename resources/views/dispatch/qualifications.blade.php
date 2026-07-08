{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : qualifications.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auftrags-Qualifikationsmatrix (Feature 028, Rang 53): Anforderungen des
  Auftrags × Mitarbeitende. Zellen erfüllt/läuft ab (<30 Tage)/fehlt —
  Icon + Text zusätzlich zur Farbe (038). Datenquelle: QualificationGate.
--}}

@extends('layouts.app')
@section('title', __('Qualifikationsmatrix'))
@section('nav-title', __('Qualifikationsmatrix'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Qualifikationsmatrix') }}</x-slot:title>
        <x-slot:subtitle>
            {{ $diary->title }}@if ($date !== null) · {{ __('Stichtag') }}: {{ $date->fdate() }}@endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('diary.show', $diary)" show-label>{{ __('Zum Auftrag') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($required->isEmpty())
        <x-card>
            <p class="text-sm text-base-content/60">{{ __('Für diesen Auftrag sind keine Qualifikationen gefordert — Anforderungen lassen sich im Dispositions-Panel des Auftrags pflegen.') }}</p>
        </x-card>
    @else
        <x-card>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-base-100">{{ __('Mitarbeiter:in') }}</th>
                            @foreach ($required as $qualification)
                                <th class="text-center">{{ $qualification->abbreviation ?? $qualification->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['bg-base-200/40' => (int) $diary->assigned_user_id === (int) $row['user']->id])>
                                <td class="sticky left-0 z-10 bg-base-100 font-medium">
                                    {{ $row['user']->name }}
                                    @if ((int) $diary->assigned_user_id === (int) $row['user']->id)
                                        <x-status-badge size="xs" tone="info">{{ __('zugewiesen') }}</x-status-badge>
                                    @endif
                                </td>
                                @foreach ($required as $qualification)
                                    @php $status = $row['status'][$qualification->id] ?? 'missing'; @endphp
                                    <td class="text-center">
                                        @if ($status === 'ok')
                                            <span class="badge badge-success badge-sm gap-1"><span class="material-symbols-outlined text-xs">check</span>{{ __('erfüllt') }}</span>
                                        @elseif ($status === 'expiring')
                                            <span class="badge badge-warning badge-sm gap-1"><span class="material-symbols-outlined text-xs">schedule</span>{{ __('läuft ab') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm gap-1"><span class="material-symbols-outlined text-xs">close</span>{{ __('fehlt') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-base-content/60">
                {{ __('„Läuft ab" = gültig am Stichtag, aber Befristung endet binnen 30 Tagen.') }}
            </p>
        </x-card>
    @endif
</x-page-shell>
@endsection

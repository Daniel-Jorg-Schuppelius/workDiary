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
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>
                {{ $diary->title }}@if ($date !== null) · {{ __('Stichtag') }}: {{ $date->fdate() }}@endif
            </x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('diary.show', $diary)" show-label>{{ __('Zum Auftrag') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($required->isEmpty())
        <x-card>
            {{-- Prerequisite-Audit (MVP-181): Hinweis mit direktem CTA. --}}
            <p class="text-sm text-muted">{{ __('Für diesen Auftrag sind keine Qualifikationen gefordert — Anforderungen lassen sich im Dispositions-Panel des Auftrags pflegen.') }}</p>
            <div class="mt-3">
                <x-button :href="route('diary.show', $diary)" tone="primary" size="sm" icon="arrow_forward">
                    {{ __('prerequisites.dispatch.cta') }}
                </x-button>
            </div>
        </x-card>
    @else
        <x-card>
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th class="sticky left-0 z-10 bg-base-100">{{ __('Mitarbeiter:in') }}</th>
                            @foreach ($required as $qualification)
                                <th class="text-center">{{ $qualification->abbreviation ?? $qualification->name }}</th>
                            @endforeach
                        </tr>
                </x-slot:head>
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
                                            <span class="badge badge-success badge-sm gap-1"><x-icon name="check" class="text-xs" />{{ __('erfüllt') }}</span>
                                        @elseif ($status === 'expiring')
                                            <span class="badge badge-warning badge-sm gap-1"><x-icon name="schedule" class="text-xs" />{{ __('läuft ab') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm gap-1"><x-icon name="close" class="text-xs" />{{ __('fehlt') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
            </x-table>
            <p class="mt-2 text-xs text-muted">
                {{ __('„Läuft ab" = gültig am Stichtag, aber Befristung endet binnen 30 Tagen.') }}
            </p>
        </x-card>
    @endif
</x-page-shell>
@endsection

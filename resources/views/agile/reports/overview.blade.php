{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : overview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Management-Übersicht (Feature 064, P10/MVP-148): org-weit, aber nur
     Projekte mit Sichtrecht des Betrachters. Prognose nur bei ≥4
     vergleichbaren Wochen — sonst Hinweis statt Scheinpräzision. --}}

@extends('layouts.app')

@section('title', __('Agile Management-Übersicht'))
@section('nav-title', __('Agile Übersicht'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ __('Agile Management-Übersicht') }}</x-slot:title>
            <x-slot:subtitle>{{ __('Alle Projektboards mit Sichtrecht — Velocity, Blockierungen und empirische Prognose.') }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($rows->isEmpty())
        <x-empty-state icon="space_dashboard" framed :title="__('Keine Projektboards mit Sichtrecht vorhanden.')" />
    @else
        <x-card>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Projekt') }}</th>
                        <th>{{ __('Methode') }}</th>
                        <th>{{ __('Aktiver Sprint') }}</th>
                        <th class="text-right"><x-term glossary="velocity">{{ __('Velocity (Median)') }}</x-term></th>
                        <th class="text-right">{{ __('Ungeplante Arbeit') }}</th>
                        <th class="text-right">{{ __('Blockiert') }}</th>
                        <th>{{ __('Prognose Restarbeit') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['project']->name }}</td>
                        <td class="text-sm text-base-content/60">{{ $row['board']->method === 'scrum' ? 'Scrum' : 'Kanban' }}</td>
                        <td>{{ $row['active_sprint']?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['velocity_median'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['scope_added'] }}</td>
                        <td class="text-right tabular-nums">
                            @if ($row['blocked_count'] > 0)
                                <x-status-badge tone="error" size="xs">{{ $row['blocked_count'] }}</x-status-badge>
                            @else
                                0
                            @endif
                        </td>
                        <td class="text-sm">
                            @if ($row['forecast']['available'])
                                {{ __('P50 :p50 / P85 :p85 / P95 :p95 Wochen', [
                                    'p50' => $row['forecast']['p50'],
                                    'p85' => $row['forecast']['p85'],
                                    'p95' => $row['forecast']['p95'],
                                ]) }}
                            @else
                                <span class="text-base-content/50" title="{{ $row['forecast']['reason'] }}">{{ __('Zu wenig Historie') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <x-icon-btn icon="monitoring" tone="ghost" size="xs" :href="route('agile.reports.sprint', $row['project'])" :label="__('Sprint-Cockpit')" />
                            <x-icon-btn icon="waterfall_chart" tone="ghost" size="xs" :href="route('agile.reports.flow', $row['project'])" :label="__('Fluss-Bericht')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

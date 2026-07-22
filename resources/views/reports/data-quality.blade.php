{{--
  Created on   : Mon Jul 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : data-quality.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Datenqualitäts-Report (Feature 024 → Rang 57): Aufträge mit fehlenden
  Pflichtklassifikationen, je Domäne/Phase/Schwere.
--}}

@extends('layouts.app')
@section('title', __('Datenqualität: Pflichtklassifikationen'))
@section('nav-title', __('Datenqualität'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ __('Fehlende Pflichtklassifikationen') }}</x-slot:title>
            <x-slot:subtitle>{{ __('Aufträge im Zeitraum') }} · {{ $label }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('Aufträge mit Lücken')" :value="$entries_with_gaps"
                    :tone="$entries_with_gaps > 0 ? 'warning' : 'success'" />
        <x-kpi-tile :label="__('Harte Lücken')" :value="$by_severity['hard'] ?? 0"
                    :tone="($by_severity['hard'] ?? 0) > 0 ? 'error' : 'success'" />
        <x-kpi-tile :label="__('Weiche Lücken')" :value="$by_severity['soft'] ?? 0" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Domäne') }}</h3>
            @if (empty($by_domain))
                <p class="text-sm text-base-content/60">{{ __('Keine Lücken im gewählten Zeitraum.') }}</p>
            @else
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('Domäne') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($by_domain as $d)
                        <tr><td>{{ $d['label'] }}</td><td class="text-right tabular-nums">{{ $d['count'] }}</td></tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Nach Phase') }}</h3>
            @if (empty($by_phase))
                <p class="text-sm text-base-content/60">{{ __('Keine Lücken im gewählten Zeitraum.') }}</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($by_phase as $phase => $count)
                        <span class="badge badge-outline">{{ $phase }}: {{ $count }}</span>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Betroffene Aufträge') }}</h3>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">check_circle</span>'
                           :title="__('Keine Aufträge mit fehlenden Pflichtklassifikationen.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Auftrag') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Fehlende Klassifikationen') }}</x-table.th>
                        <x-table.th align="right">{{ __('Aktion') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">{{ $row['title'] }}</td>
                        <td class="text-base-content/70">{{ $row['date'] ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($row['gaps'] as $gap)
                                    <span @class([
                                        'badge badge-sm',
                                        'badge-error' => $gap['severity'] === 'hard',
                                        'badge-warning' => $gap['severity'] !== 'hard',
                                    ])>{{ $gap['label'] }} · {{ $gap['phase'] }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-right">
                            <x-icon-btn icon="edit_note" tone="outline" size="xs"
                                        :href="route('diary.show', $row['sqid'])" show-label>{{ __('Nachtragen') }}</x-icon-btn>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

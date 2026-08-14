{{--
  Created on   : Mon Jul 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : asset-recurring-defects.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wiederholdefekt-Statistik (Feature 009 → Rang 47): Pareto der Assets nach
  Defektzahl im Zeitraum; Assets mit >= :threshold Defekten in :windowMonths
  Monaten sind als Wiederholdefekt-Fall markiert.
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Wiederholdefekte'))
@section('nav-title', __('Drilldown: Wiederholdefekte'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>
                {{ $label }} · {{ __('Schwelle: :n Defekte in :m Monaten', ['n' => $threshold, 'm' => $windowMonths]) }}
            </x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.assets.drilldown.recurring-defects', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.assets.drilldown.recurring-defects', ['export' => 'xlsx'])"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                            :href="route('reports.assets')" show-label>{{ __('Zur Produktanalyse') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">check_circle</span>'
                           :title="__('Keine Defekte im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Asset') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Inventarnr.') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Defekte (Zeitraum)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('12 Monate') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Schweregrade') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr @class(['bg-warning/5' => $row['is_recurring']])>
                        <td class="font-medium">
                            @if ($row['asset_sqid'] !== null)
                                <a href="{{ route('assets.dossier', $row['asset_sqid']) }}" class="link">{{ $row['asset_name'] }}</a>
                            @else
                                {{ $row['asset_name'] }}
                            @endif
                        </td>
                        <td class="text-base-content/70">{{ $row['asset_no'] ?: '—' }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ $row['total'] }}</td>
                        <td class="text-right tabular-nums text-base-content/70">{{ $row['recent_total'] }}</td>
                        <td class="text-xs text-base-content/70">
                            @foreach ($row['by_severity'] as $sev => $count)
                                <span class="whitespace-nowrap">{{ \App\Enums\Asset\DefectSeverity::from($sev)->label() }}: {{ $count }}</span>@if (! $loop->last), @endif
                            @endforeach
                        </td>
                        <td>
                            @if ($row['is_recurring'])
                                <x-status-badge tone="warning" size="sm">{{ __('Wiederholdefekt') }}</x-status-badge>
                            @else
                                <span class="text-base-content/40">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

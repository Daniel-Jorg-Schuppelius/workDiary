{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : hoai-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kostenermittlung: :name', ['name' => $project->name]))
@section('nav-title', __('Kostenermittlung'))

@php
    $money = static fn (?float $value): string => $value === null
        ? '—'
        : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true) . ' €';
    $stages = \App\Models\Costing\CostEstimate::STAGES;
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">
                {{ $project->name }} · {{ __('Die vier Stufen stehen nebeneinander — ihr Vergleich ist die Kostenkontrolle.') }}
            </div>
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm" show-label
                            :href="route('projects.hoai-report', [$project, 'export' => 'pdf'])">PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (empty($report['rows']))
        <x-empty-state framed icon="stacked_bar_chart"
                       :title="__('Für dieses Projekt liegt keine Kostenermittlung vor.')"
                       :message="__('Kostenschätzung und -berechnung kommen als GAEB X51 herein; Kostenanschlag und -feststellung erzeugt WorkDiary aus dem Leistungsverzeichnis.')" />
    @else
        <x-card>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kostengruppe') }}</th>
                        @foreach ($stages as $stage)
                            <th class="text-right">{{ __('costing.stage.' . $stage) }}</th>
                        @endforeach
                        <th class="text-right">{{ __('Abweichung') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>
                            @if ($row['code'] === '')
                                <span class="text-base-content/70">{{ $row['label'] }}</span>
                            @else
                                <span class="font-mono">{{ $row['code'] }}</span> {{ $row['label'] }}
                            @endif
                        </td>
                        @foreach ($stages as $stage)
                            {{-- Fehlt eine Stufe, bleibt die Spalte leer: Sie aus der
                                 Nachbarstufe zu füllen erfände eine Ermittlung. --}}
                            <td class="text-right tabular-nums">{{ $money($row['amounts'][$stage]) }}</td>
                        @endforeach
                        <td class="text-right tabular-nums @if (($row['delta'] ?? 0) > 0) text-error @elseif (($row['delta'] ?? 0) < 0) text-success @endif">
                            {{ $money($row['delta']) }}
                        </td>
                    </tr>
                @endforeach
                <tr class="border-t-2 border-base-300 font-medium">
                    <td>{{ __('Gesamt') }}</td>
                    @foreach ($stages as $stage)
                        <td class="text-right tabular-nums">{{ $money($report['totals'][$stage]) }}</td>
                    @endforeach
                    <td class="text-right tabular-nums">{{ $money($report['delta']) }}</td>
                </tr>
            </x-table>

            <p class="mt-3 text-sm text-base-content/70">
                {{ __('Die Abweichung vergleicht die erste mit der letzten vorhandenen Stufe. Mit weniger als zwei Stufen gibt es nichts zu vergleichen.') }}
            </p>
        </x-card>

        <x-card :title="__('Stände')">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Stufe') }}</th>
                        <th>{{ __('Bezeichnung') }}</th>
                        <th>{{ __('Stand') }}</th>
                        <th>{{ __('Herkunft') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($stages as $stage)
                    @php $estimate = $report['stages'][$stage]; @endphp
                    <tr @class(['opacity-60' => $estimate === null])>
                        <td>{{ __('costing.stage.' . $stage) }}</td>
                        <td>{{ $estimate?->name ?? '—' }}</td>
                        <td class="tabular-nums">{{ $estimate?->determined_on->format('d.m.Y') ?? '—' }}</td>
                        <td class="text-xs text-base-content/70">
                            {{ $estimate === null ? __('nicht ermittelt') : __('costing.source.' . $estimate->source) }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cloud-intake.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Importbericht Cloud-Dokumenteingang (Feature 080 P9): Durchsatz, Abweisungen
  und Verbindungslage im gewählten Zeitraum.
--}}

@extends('layouts.app')
@section('title', __('cloud_intake.report.title'))
@section('nav-title', __('cloud_intake.report.nav'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ __('cloud_intake.report.subtitle') }} · {{ $label }}</x-slot:subtitle>
            <x-slot:actions>
                <a class="btn btn-sm btn-ghost" href="{{ route('reports.cloud-intake', ['export' => 'csv']) }}">
                    <span class="material-symbols-outlined" aria-hidden="true">download</span>
                    <span>{{ __('CSV') }}</span>
                </a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-kpi-tile :label="__('cloud_intake.report.kpi.total')" :value="$total" />
        <x-kpi-tile :label="__('cloud_intake.report.kpi.imported')" :value="$byStatus['imported'] ?? 0" tone="success" />
        <x-kpi-tile :label="__('cloud_intake.report.kpi.inbox')" :value="$byStatus['inbox'] ?? 0"
                    :tone="($byStatus['inbox'] ?? 0) > 0 ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('cloud_intake.report.kpi.rejected')" :value="$byStatus['rejected'] ?? 0"
                    :tone="($byStatus['rejected'] ?? 0) > 0 ? 'error' : 'success'" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('cloud_intake.report.chart.per_period', ['per' => $periodPhrase])" :unit="__('cloud_intake.report.unit.documents')"
                      :series="$perPeriod" :x-label="$periodAxis" :y-label="__('cloud_intake.report.unit.documents')" />
        <x-charts.bar-h :title="__('cloud_intake.report.chart.by_provider')" :unit="__('cloud_intake.report.unit.documents')"
                        :series="$byProvider" :x-label="__('cloud_intake.report.column.provider')" :y-label="__('cloud_intake.report.unit.documents')" />
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('cloud_intake.report.section.connections') }}</h3>
        @if (empty($connections))
            <p class="text-sm text-base-content/60">{{ __('cloud_intake.report.empty.connections') }}</p>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.folder') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.provider') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('cloud_intake.report.column.imported') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('cloud_intake.report.column.rejected') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.last_run') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($connections as $connection)
                    <tr>
                        <td class="truncate">{{ $connection['label'] }}</td>
                        <td>{{ $connection['provider'] }}</td>
                        <td>{{ $connection['status'] }}</td>
                        <td class="text-right tabular-nums">{{ $connection['imported'] }}</td>
                        <td class="text-right tabular-nums">{{ $connection['rejected'] }}</td>
                        <td>{{ $connection['lastRun'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('cloud_intake.report.section.reasons') }}</h3>
        @if (empty($byReason))
            <p class="text-sm text-base-content/60">{{ __('cloud_intake.report.empty.reasons') }}</p>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.reason') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('cloud_intake.report.column.count') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($byReason as $reason)
                    <tr>
                        <td>{{ $reason['reason'] }}</td>
                        <td class="text-right tabular-nums">{{ $reason['count'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('cloud_intake.report.section.items') }}</h3>
        @if (empty($rows))
            <p class="text-sm text-base-content/60">{{ __('cloud_intake.report.empty.items') }}</p>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.date') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.provider') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.path') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.status') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('cloud_intake.report.column.reason') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap">{{ $row['date'] }}</td>
                        <td>{{ $row['provider'] }}</td>
                        <td class="truncate">{{ $row['path'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td class="text-base-content/70">{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

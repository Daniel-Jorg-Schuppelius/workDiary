{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : safety.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('safety.report.title'))
@section('nav-title', __('safety.report.nav'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('safety.report.subtitle')" />
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.safety')" :reset="route('reports.safety')">
        @include('reports._standard_filters', ['idPrefix' => 'safety'])
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        <x-kpi-tile :label="__('safety.report.kpi.total')" :value="$total" />
        <x-kpi-tile :label="__('safety.report.kpi.open')" :value="$open" tone="warning" />
        <x-kpi-tile :label="__('safety.report.kpi.closed')" :value="$closed" tone="success" />
        <x-kpi-tile :label="__('safety.report.kpi.critical')" :value="$bySeverity['critical']" tone="error" />
    </div>

    <div class="chart-grid mt-4 grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Ereignisse :per', ['per' => $periodPhrase])" :unit="__('Ereignisse')"
                      :series="$monthlySeries" :x-label="$periodAxis" :y-label="__('Ereignisse')"
                      :y2-label="__('davon geschlossen')" />
        <x-charts.stacked-bar :title="__('Ereignisse :per nach Status', ['per' => $periodPhrase])" :unit="__('Ereignisse')"
                              :series="$statusMonthlySeries" :bands="$statusBands" :x-label="$periodAxis" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('safety.report.by_kind') }}</h3>
            <x-detail-grid>
                @foreach (\App\Enums\Safety\SafetyEventKind::cases() as $kind)
                    <x-detail-grid.row :label="$kind->label()" :value="(string) $byKind[$kind->value]" />
                @endforeach
            </x-detail-grid>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('safety.report.by_severity') }}</h3>
            <x-detail-grid>
                @foreach (\App\Enums\Safety\SafetyEventSeverity::cases() as $severity)
                    <x-detail-grid.row :label="$severity->label()" :value="(string) $bySeverity[$severity->value]" />
                @endforeach
            </x-detail-grid>
        </x-card>
    </div>
</x-page-shell>
@endsection

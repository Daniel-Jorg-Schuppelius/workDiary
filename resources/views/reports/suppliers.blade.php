{{--
  Created on   : Fri Aug 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : suppliers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Lieferantenanalyse'))
@section('nav-title', __('Lieferantenanalyse'))

@section('content')
@php
    $eur = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $linkParams = array_filter([
        'min_spend' => $minSpend > 0 ? $minSpend : null,
        'hide_zero' => $hideZero ? 1 : null,
    ]);
    $hhi = $concentration['hhi'];
    $hhiTone = $hhi === null ? 'neutral'
        : ($hhi > \App\Services\Reporting\SupplierAnalysisReportBuilder::HHI_HIGH ? 'error'
        : ($hhi >= \App\Services\Reporting\SupplierAnalysisReportBuilder::HHI_MODERATE ? 'warning' : 'success'));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Ausgaben, Beschaffungsvolumen und Klumpenrisiko je Lieferant.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.suppliers', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.suppliers', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.supplier-analysis" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.suppliers')" :reset="route('reports.suppliers')">
        <x-filter-field :label="__('Mindest-Ausgaben (€)')" for="rep-min-spend">
            <input id="rep-min-spend" type="number" name="min_spend" value="{{ $minSpend }}" min="0" class="input input-sm input-bordered w-36" />
        </x-filter-field>
        <label class="flex shrink-0 cursor-pointer items-center gap-2" for="suppliers-hide-zero"
               title="{{ __('Nur Lieferanten mit Aktivität im Zeitraum anzeigen (ohne reine Nullzeilen).') }}">
            <input type="checkbox" id="suppliers-hide-zero" name="hide_zero" value="1"
                   @checked($hideZero) class="toggle toggle-primary toggle-sm" data-autosubmit>
            <span class="text-sm text-base-content/75">{{ __('Lieferanten ohne Werte ausblenden') }}</span>
        </label>
    </x-filter-bar>

    @unless ($withProcurement)
        <div class="alert alert-info text-xs">
            <x-icon name="info" />
            <span>{{ __('Bestell- und Termindaten erscheinen zusätzlich, sobald das Lager-Modul aktiv ist. Ausgaben stammen aus dem Belegspiegel der Buchhaltung.') }}</span>
        </div>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-tile :label="__('Ausgaben gesamt')" :value="$eur($concentration['totalSpend'])" tone="primary" />
        <x-kpi-tile :label="__('Aktive Lieferanten')" :value="$concentration['activeSuppliers']" tone="info" />
        <x-kpi-tile :label="__('Top-5-Anteil')" :value="$concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–'"
                    :tone="($concentration['top5Share'] ?? 0) > 60 ? 'warning' : 'neutral'"
                    :hint="__('Klumpenrisiko ab ~60 %')" />
        <x-kpi-tile :label="__('HHI (Konzentration)')" :value="$hhi ?? '–'" :tone="$hhiTone" term="hhi"
                    :hint="__('unter 1500 unkritisch, über 2500 hoch')" />
        <x-kpi-tile :label="__('Offener Betrag')" :value="$eur(collect($rows)->sum('openAmount'))"
                    :hint="__('Offene Verbindlichkeiten aus dem Belegspiegel')" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Ausgaben je Lieferant (Top 20)')" unit="€" :series="$spendSeries" :x-label="__('Lieferant')" y-label="€"
                         :note="__('Datenbasis: Einkaufsbelege im Zeitraum; Klick öffnet die Lieferanten-Detailseite.')" />
        <x-charts.bar :title="__('Ausgaben je Monat (12 Monate)')" unit="€" :series="$monthlySpendSeries" :x-label="__('Monat')" :y-label="__('Ausgaben')"
                      :note="__('Org-weite Gesamtausgaben je Monat — unabhängig vom gewählten Zeitraumfilter.')" />
    </div>
    <x-charts.bar-h :title="__('Offener Betrag je Lieferant (Top 15)')" unit="€" :series="$openSeries" :x-label="__('Lieferant')" :y-label="__('Offener Betrag')"
                    :note="__('Offene Verbindlichkeiten aus nicht vollständig bezahlten Einkaufsbelegen.')" />

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if ($rows->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>' :title="__('Keine Lieferantendaten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Lieferant') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ausgaben') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Belege') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ø Beleg') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Offener Betrag') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tage seit Beleg') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Trend %') }}</x-table.th>
                        @if ($withProcurement)
                            <x-table.th sort type="number" align="right">{{ __('Bestellungen') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Offene Bestellungen') }}</x-table.th>
                        @endif
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    @php($supplierUrl = route('suppliers.show', \App\Support\Sqid::encode(\App\Models\Supplier::class, $row['supplierId'])))
                    <tr>
                        <td class="font-medium">
                            <a href="{{ $supplierUrl }}" class="link link-hover">{{ $row['supplierName'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $eur($row['spend']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['voucherCount'] }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['avgVoucher']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['openAmount'] > 0 ? $eur($row['openAmount']) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['recencyDays'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">
                            @if ($row['trendPct'] === null)
                                —
                            @else
                                <span class="{{ $row['trendPct'] > 0 ? 'text-error' : ($row['trendPct'] < 0 ? 'text-success' : 'text-base-content/70') }}">
                                    {{ ($row['trendPct'] > 0 ? '+' : '') . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['trendPct'], 1) }} %
                                </span>
                            @endif
                        </td>
                        @if ($withProcurement)
                            <td class="text-right tabular-nums">{{ $row['orderCount'] ?? 0 }}</td>
                            <td class="text-right tabular-nums">{{ $row['openOrderCount'] ?? 0 }}</td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

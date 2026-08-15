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
    $supplierUrl = fn (int $id): string => route('suppliers.show', \App\Support\Sqid::encode(\App\Models\Supplier::class, $id));
    $voucherRangeUrl = fn (int $id): string => route('suppliers.show', [
        'supplier' => \App\Support\Sqid::encode(\App\Models\Supplier::class, $id),
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]) . '#vouchers';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Ausgaben, Beschaffungsvolumen und Klumpenrisiko je Lieferant.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.suppliers', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.suppliers', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.suppliers', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.supplier-analysis" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.suppliers')" :reset="route('reports.suppliers')">
        <x-filter-field :label="__('Mindest-Ausgaben (€)')" for="rep-min-spend" inline>
            <input id="rep-min-spend" type="number" name="min_spend" value="{{ $minSpend }}" min="0" class="input input-sm input-bordered w-24" />
        </x-filter-field>
        <x-filter-toggle name="hide_zero" id="suppliers-hide-zero"
                         :label="__('Lieferanten ohne Werte ausblenden')"
                         :title="__('Nur Lieferanten mit Aktivität im Zeitraum anzeigen (ohne reine Nullzeilen).')"
                         :checked="$hideZero" data-autosubmit />
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
        <x-charts.bar :title="__('Ausgaben :per', ['per' => $periodPhrase])" unit="€" :series="$monthlySpendSeries" :x-label="$periodAxis" :y-label="__('Ausgaben')"
                      :note="__('Org-weite Gesamtausgaben im gewählten Zeitraum.')" />
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
                    @php($voucherUrl = $voucherRangeUrl($row['supplierId']))
                    <tr>
                        <td class="font-medium">
                            <a href="{{ $supplierUrl($row['supplierId']) }}" class="link link-hover" title="{{ __('Lieferantenakte öffnen') }}">{{ $row['supplierName'] }}</a>
                            <a href="{{ $voucherUrl }}" class="text-base-content/50 hover:text-base-content"
                               aria-label="{{ __('Belege im Zeitraum öffnen') }}" title="{{ __('Belege im Zeitraum öffnen') }}">
                                <span class="material-symbols-outlined text-[14px] align-middle" aria-hidden="true">calendar_month</span>
                            </a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ $voucherUrl }}" class="link link-hover">{{ $eur($row['spend']) }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ $voucherUrl }}" class="link link-hover">{{ $row['voucherCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $eur($row['avgVoucher']) }}</td>
                        <td class="text-right tabular-nums">
                            @if ($row['openAmount'] > 0)
                                <a href="{{ $voucherUrl }}" class="link link-hover">{{ $eur($row['openAmount']) }}</a>
                            @else
                                —
                            @endif
                        </td>
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

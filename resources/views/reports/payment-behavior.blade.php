{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : payment-behavior.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Zahlungsverhalten'))
@section('nav-title', __('Zahlungsverhalten'))

@section('content')
@php
    $linkParams = array_filter($standardFilters->toQueryParams());
    $eur = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $days = fn (?float $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1);
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('DSO-Trend, Zahldauer und überfällige Forderungen — Verhaltenssicht auf lokale Rechnungen.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.payment-behavior', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.payment-behavior', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.payment-behavior', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.payment-behavior" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.payment-behavior')" :reset="route('reports.payment-behavior')">
        @include('reports._standard_filters', ['idPrefix' => 'payment-behavior'])
    </x-filter-bar>

    @if (! $hasData)
        <div class="alert alert-info text-sm" role="status">
            <x-icon name="info" />
            {{ __('Keine Rechnungsdaten: weder lokale Rechnungen noch gespiegelte Lexoffice-Belege vorhanden. Bei externer Rechnungshoheit zuerst den Beleg-Sync des Lexoffice-Plugins ausführen — er lädt auch die Zahlungsdaten nach.') }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-kpi-tile :label="__('DSO (Tage)')" :value="$days($kpis['dso'])" term="dso"
                        :tone="($kpis['dso'] ?? 0) > 45 ? 'warning' : 'neutral'"
                        :hint="__('offene Forderungen ÷ Umsatz der letzten 90 Tage × 90')" />
            <x-kpi-tile :label="__('Ø Zahldauer (Tage)')" :value="$days($kpis['avgPayDays'])"
                        :hint="__(':n bezahlte Rechnungen im Zeitraum', ['n' => $kpis['paidCount']])" />
            <x-kpi-tile :label="__('Pünktlich bezahlt')" :value="$kpis['onTimeShare'] !== null ? $days($kpis['onTimeShare']) . ' %' : '—'" />
            <x-kpi-tile :label="__('Überfällige Rechnungen')" :value="$kpis['overdueCount']"
                        :tone="$kpis['overdueCount'] > 0 ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('Überfälliges Volumen')" :value="$eur($kpis['overdueTotal'])"
                        :tone="$kpis['overdueTotal'] > 0 ? 'warning' : 'success'" />
        </div>

        <div class="chart-grid grid gap-3 xl:grid-cols-2">
            <x-charts.line :title="__('DSO im Monatsverlauf')" :unit="__('Tage')" :series="$dsoSeries"
                           :x-label="__('Monat')" :y-label="__('DSO (Tage)')"
                           :note="__('DSO je Monatsende: offene Forderungen ÷ Umsatz der letzten 90 Tage × 90 — je höher, desto länger ist Liquidität gebunden.')" />
            <x-charts.line :title="__('Ø Zahldauer im Monatsverlauf')" :unit="__('Tage')" :series="$payDaysSeries"
                           :x-label="__('Monat')" :y-label="__('Zahldauer (Tage)')"
                           :note="__('Ø Tage von Rechnungsstellung bis Zahlung der im jeweiligen Monat bezahlten Rechnungen.')" />
        </div>
        <div class="chart-grid grid gap-3 xl:grid-cols-2">
            <x-charts.boxplot :title="__('Zahldauer-Verteilung (Ausstellung bis Zahlung)')" :unit="__('Tage')" :series="$payBox"
                              :x-label="__('Kunde')" :y-label="__('Tage')"
                              :note="__('Nur bezahlte Rechnungen im Zeitraum; Klick auf einen Kunden filtert diesen Bericht auf ihn.')" />
            <x-charts.bar-h :title="__('Ø Verzugstage je Kunde (Top 10)')" :unit="__('Tage')" :series="$delaySeries"
                            :x-label="__('Kunde')" :y-label="__('Verzugstage')"
                            :note="__('Verzug = Tage nach Fälligkeit (Frühzahler zählen als 0); Klick filtert diesen Bericht auf den Kunden.')" />
        </div>

        <x-card class="mt-4">
            <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Überfällige offene Rechnungen (Top 15)') }}</h2>
            <div class="mb-3 text-xs text-muted">{{ __('Zeitraum') }}: {{ $label }} · {{ __('Stichtag: Zeitraumende') }}</div>

            @if ($overdue === [])
                <p class="text-sm text-muted">{{ __('Keine überfälligen offenen Rechnungen — gut so.') }}</p>
            @else
                <x-table bare table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('Rechnung') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                            <x-table.th sort type="string" align="right">{{ __('Fällig am') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Tage überfällig') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($overdue as $row)
                        <tr>
                            <td class="font-medium">
                                @if ($row['invoiceId'] !== null)
                                    <a href="{{ route('invoices.show', \App\Support\Sqid::encode(\App\Models\Invoice::class, $row['invoiceId'])) }}" class="link link-hover">{{ $row['number'] }}</a>
                                @else
                                    {{ $row['number'] }}
                                    <span class="badge badge-ghost badge-xs ml-1">Lexoffice</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('invoices.index', ['customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $row['customerId']), 'status' => \App\Models\Invoice::STATUS_ISSUED]) }}" class="link link-hover">{{ $row['customerName'] }}</a>
                            </td>
                            <td class="text-right tabular-nums">{{ $row['dueOn'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['daysOverdue'] }}</td>
                            <td class="text-right tabular-nums">{{ $eur($row['total']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
</x-page-shell>
@endsection

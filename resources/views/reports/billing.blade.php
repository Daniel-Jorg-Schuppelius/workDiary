@extends('layouts.app')
@section('title', __('Abrechnung'))
@section('nav-title', __('Abrechnungs-Auswertung'))

@section('content')
@php
    $eur = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration(abs($minutes));
    $statusLabels = [
        'draft'     => __('Entwurf'),
        'issued'    => __('Ausgestellt'),
        'paid'      => __('Bezahlt'),
        'cancelled' => __('Storniert'),
    ];
    $agingLabels = [
        'current'  => __('Aktuell'),
        '1_7'      => __('1–7 Tage'),
        '8_14'     => __('8–14 Tage'),
        '15_30'    => __('15–30 Tage'),
        '30_plus'  => __('> 30 Tage'),
    ];
    $totalIssuedPaid = ($status['issued']['total'] ?? 0) + ($status['paid']['total'] ?? 0);
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Rechnungs-Status, Aging offener Posten und projizierter Erlös aus unbillter Zeit.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.billing', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.billing', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.billing')" :reset="route('reports.billing')">
        @include('reports._standard_filters', ['idPrefix' => 'billing'])
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Ausgestellt + Bezahlt (Σ Brutto)')" :value="$eur($totalIssuedPaid)" />
        <x-kpi-tile :label="__('Offene Forderungen')" :value="$eur($aging['open_total'])"
                    :tone="$aging['buckets']['30_plus']['count'] > 0 ? 'error' : 'neutral'"
                    :hint="$aging['buckets']['30_plus']['count'] . ' ' . __('> 30 Tage')" />
        <x-kpi-tile :label="__('Unbillte Zeit')" :value="$fmtMin($unbilled['minutes'])"
                    :hint="$unbilled['count'] . ' ' . __('Einträge') . ' · ' . $eur($unbilled['projected_revenue'])" />
    </div>

    {{-- Feature 002: Diagramme (Abrechenbarkeit je Monat + Umsatz-Pareto) --}}
    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.stacked-bar :title="__('Abrechenbare und nicht abrechenbare Stunden je Monat')" unit="h" :series="$monthlyBillableSeries" :bands="$billableBands" :x-label="__('Monat')" />
        <x-charts.pareto :title="__('Umsatz je Kunde (Top 15)')" unit="€" :series="$customerRevenueSeries" :x-label="__('Kunde')" :y-label="__('Brutto (€)')" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Rechnungen nach Status') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Netto') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Brutto') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($status as $st => $s)
                    <tr>
                        <td>{{ $statusLabels[$st] ?? $st }}</td>
                        <td class="text-right tabular-nums">{{ $s['count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $s['subtotal'] }}">{{ $eur($s['subtotal']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $s['total'] }}">{{ $eur($s['total']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Aging – offene Posten') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Bucket') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Summe') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Offen gesamt') }}</td>
                        <td></td>
                        <td class="text-right tabular-nums">{{ $eur($aging['open_total']) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($aging['buckets'] as $k => $b)
                    <tr class="{{ $k === '30_plus' && $b['count'] > 0 ? 'text-error font-semibold' : '' }}">
                        <td>{{ $agingLabels[$k] ?? $k }}</td>
                        <td class="text-right tabular-nums">{{ $b['count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $b['total'] }}">{{ $eur($b['total']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Top-Kunden (ausgestellt + bezahlt im Zeitraum)') }}</h3>
        @if (empty($perCustomer))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">payments</span>' :title="__('Keine Rechnungen im Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Rechnungen') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Brutto') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($perCustomer as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['customer']->name }}</td>
                        <td class="text-right tabular-nums">{{ $r['count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['total'] }}">{{ $eur($r['total']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Eingangs-/Validierungs-/Übergabe-Berichte (Feature 066, MVP-169) --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Eingangs-E-Rechnungen (im Zeitraum)') }}</h3>
            @if (empty($einvoicing['incoming']))
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">move_to_inbox</span>' :title="__('Keine Eingänge im Zeitraum.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Anzahl') }}</th>
                            <th class="text-right">{{ __('Brutto') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($einvoicing['incoming'] as $st => $row)
                        <tr>
                            <td>{{ __("values.$st") }}</td>
                            <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                            <td class="text-right tabular-nums">{{ $eur($row['gross']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <p class="mt-2 text-sm text-base-content/70">{{ __('An Buchhaltung übergeben: :count', ['count' => $einvoicing['incoming_transferred']]) }}</p>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Eingangs-Validierung & Mahnstufen') }}</h3>
            <x-detail-grid>
                <x-detail-grid.row :label="__('Validierung geprüft')">{{ $einvoicing['validation']['checked'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Validierung bestanden')">{{ $einvoicing['validation']['passed'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Validierung fehlgeschlagen')">{{ $einvoicing['validation']['failed'] }}</x-detail-grid.row>
                @foreach ($einvoicing['dunning'] as $level => $count)
                    <x-detail-grid.row :label="__('Offene Rechnungen in Mahnstufe :level', ['level' => $level])">{{ $count }}</x-detail-grid.row>
                @endforeach
            </x-detail-grid>
        </x-card>

        {{-- Vollaudit 2026-07 (N18): Angebots-/Belegketten-Kennzahlen. --}}
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Angebote & Belegkette (im Zeitraum)') }}</h3>
            <x-detail-grid>
                @forelse ($documentChain['quotes'] as $st => $count)
                    <x-detail-grid.row :label="__('Angebote: :status', ['status' => $st])">{{ $count }}</x-detail-grid.row>
                @empty
                    <x-detail-grid.row :label="__('Angebote')">{{ __('Keine im Zeitraum.') }}</x-detail-grid.row>
                @endforelse
                <x-detail-grid.row :label="__('Annahmequote')">{{ $documentChain['acceptance_rate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($documentChain['acceptance_rate'], 1, withThousandsSeparator: true) . ' %' : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Median Erstellung → Entscheidung')">{{ $documentChain['decision_median_days'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($documentChain['decision_median_days'], 1, withThousandsSeparator: true) . ' ' . __('Tage') : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Angebot → Rechnung')">{{ $documentChain['conversions']['quote_to_invoice'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Pro-forma → Rechnung')">{{ $documentChain['conversions']['proforma_to_invoice'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Stornos / Gutschriften')">{{ $documentChain['correction']['cancellations'] }} / {{ $documentChain['correction']['credit_notes'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Korrekturquote')">{{ $documentChain['correction']['rate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($documentChain['correction']['rate'], 1, withThousandsSeparator: true) . ' %' : '—' }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>
    </div>
</x-page-shell>
@endsection

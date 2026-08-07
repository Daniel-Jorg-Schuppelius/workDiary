{{--
  Created on   : Fri Aug 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : supplier-value.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Lieferantenwert'))
@section('nav-title', __('Lieferantenwert'))

@section('content')
@php
    $eur = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $linkParams = array_filter(['risk_share' => $riskShare !== 15.0 ? $riskShare : null]);
    $hhi = $concentration['hhi'];
    $hhiTone = $hhi === null ? 'neutral'
        : ($hhi > \App\Services\Reporting\SupplierValueReportBuilder::HHI_HIGH ? 'error'
        : ($hhi >= \App\Services\Reporting\SupplierValueReportBuilder::HHI_MODERATE ? 'warning' : 'success'));
    $segmentBadge = [
        'strategic' => 'badge-success',
        'core' => 'badge-info',
        'occasional' => 'badge-ghost',
        'new' => 'badge-primary',
        'lapsed' => 'badge-warning',
        'dormant' => 'badge-ghost',
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('RFM-Segmente, Ausgabenkonzentration und Klumpenrisiko im Einkauf.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.supplier-value', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.supplier-value', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.supplier-value" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.supplier-value')" :reset="route('reports.supplier-value')">
        <x-filter-field :label="__('Risiko-Schwelle (Ausgabenanteil %)')" for="sv-risk-share">
            <input id="sv-risk-share" type="number" name="risk_share" value="{{ $riskShare }}" min="1" step="1" class="input input-sm input-bordered w-36" />
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-tile :label="__('Ausgaben gesamt')" :value="$eur($concentration['totalSpend'])" tone="primary" />
        <x-kpi-tile :label="__('Top-5-Anteil')" :value="$concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–'"
                    :tone="($concentration['top5Share'] ?? 0) > 60 ? 'warning' : 'neutral'"
                    :hint="__('Klumpenrisiko ab ~60 %')" />
        <x-kpi-tile :label="__('Top-10-Anteil')" :value="$concentration['top10Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top10Share'], 1) . ' %' : '–'" />
        <x-kpi-tile :label="__('HHI (Konzentration)')" :value="$hhi ?? '–'" :tone="$hhiTone" term="hhi"
                    :hint="__('unter 1500 unkritisch, über 2500 hoch')" />
        <x-kpi-tile :label="__('Kritische A-Lieferanten')" :value="count($riskRows)"
                    :tone="count($riskRows) > 0 ? 'warning' : 'success'"
                    :hint="__('Ausgabenanteil ab :share %', ['share' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($riskShare, 0)])" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Ausgaben je Lieferant (Top 20)')" unit="€" :series="$spendSeries" :x-label="__('Lieferant')" y-label="€"
                         :note="__('Datenbasis: Einkaufsbelege im Zeitraum; Klick öffnet die Lieferanten-Detailseite.')" />
        <x-charts.scatter :title="__('Ausgaben nach Inaktivität (rechts = länger her)')" unit="€"
                          :series="$dependencyScatter['series']" :percentiles="$dependencyScatter['percentiles']"
                          :x-label="__('Lieferant (Tage seit letztem Beleg)')" y-label="€"
                          :note="__('Punkte rechts oben = ausgabenstarke Lieferanten, die lange nichts geliefert haben; P80 = 80. Ausgaben-Perzentil.')" />
    </div>
    <x-charts.bar-h :title="__('Lieferanten je Segment')" :unit="__('Lieferanten')" :series="$segmentSeries" :x-label="__('Segment')" :y-label="__('Lieferanten')"
                    :note="__('Klick auf ein Segment filtert die Lieferantenliste unten auf genau diese Lieferanten.')" />

    <x-card class="mt-4">
        <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Kritische A-Lieferanten (Klumpenrisiko)') }}</h2>
        @if (count($riskRows) === 0)
            <p class="text-sm text-base-content/60">{{ __('Kein Lieferant überschreitet den eingestellten Ausgabenanteil — gute Streuung.') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Lieferant') }}</th>
                        <th class="text-right">{{ __('Ausgaben im Zeitraum') }}</th>
                        <th class="text-right">{{ __('Ausgabenanteil') }}</th>
                        <th>{{ __('Ausgabenverlauf (12 Monate)') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($riskRows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('suppliers.show', \App\Support\Sqid::encode(\App\Models\Supplier::class, $row['supplierId'])) }}" class="link link-hover">
                                {{ $row['supplierName'] }}
                            </a>
                        </td>
                        <td class="text-right tabular-nums">{{ $eur($row['spend']) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['spendShare'], 1) }} %</td>
                        <td><x-charts.sparkline :values="$riskSparklines[$row['supplierId']] ?? []" unit="€" :label="__('Monatsausgaben')" /></td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card id="lieferantenliste" class="mt-4 scroll-mt-24">
        <div class="mb-2 flex flex-wrap items-center gap-3">
            <div class="text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>
            @if ($segment !== null)
                <span class="badge badge-sm {{ $segmentBadge[$segment] ?? 'badge-ghost' }}">
                    {{ __('Segment') }}: {{ $segmentLabels[$segment] ?? $segment }}
                </span>
                <a href="{{ route('reports.supplier-value', $linkParams) }}#lieferantenliste" class="link text-xs">{{ __('Segmentfilter aufheben') }}</a>
            @endif
        </div>

        <details class="mb-3 rounded-box border border-base-300 bg-base-200/40 p-3 text-sm">
            <summary class="cursor-pointer font-medium">{{ __('Wie entstehen die Segmente?') }}</summary>
            <p class="mt-2 text-base-content/70">
                {{ __('Jeder aktive Lieferant erhält drei Quintil-Scores von 1 (unterstes Fünftel) bis 5 (oberstes Fünftel): R (Recency: je kürzer der letzte Beleg her ist, desto höher), F (Frequency: Belegtage im Zeitraum) und M (Monetary: Ausgaben im Zeitraum). Die erste zutreffende Regel bestimmt das Segment:') }}
            </p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-base-content/70">
                <li><span class="badge badge-ghost badge-sm">{{ $segmentLabels['dormant'] }}</span> — {{ __('keine Belege im Zeitraum, oder R ≤ 2 ohne hohe Ausgaben') }}</li>
                <li><span class="badge badge-primary badge-sm">{{ $segmentLabels['new'] }}</span> — {{ __('Erster Beleg liegt im Zeitraum') }}</li>
                <li><span class="badge badge-success badge-sm">{{ $segmentLabels['strategic'] }}</span> — {{ __('R ≥ 4 und F ≥ 4 und M ≥ 4') }}</li>
                <li><span class="badge badge-warning badge-sm">{{ $segmentLabels['lapsed'] }}</span> — {{ __('R ≤ 2 bei M ≥ 4 (ausgabenstark, aber lange keine Belege)') }}</li>
                <li><span class="badge badge-info badge-sm">{{ $segmentLabels['core'] }}</span> — {{ __('F ≥ 3 (regelmäßige Beschaffung)') }}</li>
                <li><span class="badge badge-ghost badge-sm">{{ $segmentLabels['occasional'] }}</span> — {{ __('alle übrigen aktiven Lieferanten') }}</li>
            </ul>
        </details>

        @if ($tableRows->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>' :title="__('Keine Lieferantendaten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Lieferant') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Segment') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tage seit Beleg') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Belegtage') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ausgaben') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anteil %') }}</x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="rfm_recency">R</x-term></x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="rfm_frequency">F</x-term></x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="rfm_monetary">M</x-term></x-table.th>
                        <x-table.th sort type="string" align="right">{{ __('Erster Beleg') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($tableRows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('suppliers.show', \App\Support\Sqid::encode(\App\Models\Supplier::class, $row['supplierId'])) }}" class="link link-hover">
                                {{ $row['supplierName'] }}
                            </a>
                        </td>
                        <td><span class="badge badge-sm {{ $segmentBadge[$row['segment']] ?? 'badge-ghost' }}">{{ $segmentLabels[$row['segment']] ?? $row['segment'] }}</span></td>
                        <td class="text-right tabular-nums">{{ $row['recencyDays'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['frequencyDays'] }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['spend']) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['spendShare'], 1) }} %</td>
                        <td class="text-right tabular-nums">{{ $row['r'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['f'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['m'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['firstActivity'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

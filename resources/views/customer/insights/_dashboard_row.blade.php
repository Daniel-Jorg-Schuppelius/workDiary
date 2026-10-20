{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dashboard_row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kennzahlenzeile „Dieses Jahr" auf der Startseite (MVP-1243) — nur mit Freigabe „Auswertungen";
     keine Diagramme auf der Startseite (E13). Erwartet: $insights (PortalInsights::summary), $year. --}}
@php
    $money = fn (float $amount, string $currency): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) . ' ' . $currency;
@endphp
<section class="mt-6" aria-labelledby="portal-insights-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 id="portal-insights-title" class="text-base font-semibold">{{ __('customer_portal.insights.dashboard_title', ['year' => $year]) }}</h2>
        <x-button :href="route('customer.insights.index', ['year' => $year])" tone="ghost" size="sm" icon="insights">{{ __('customer_portal.insights.dashboard_link') }}</x-button>
    </div>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @if ($insights['minutes'] !== null)
            <x-kpi-tile :label="__('customer_portal.insights.kpi_hours', ['year' => $year])" :value="\App\Support\Formats::duration($insights['minutes'], 'clock')" format="text" tone="primary" :href="route('customer.insights.index', ['year' => $year])" />
        @endif
        @if ($insights['gross'] !== null)
            <x-kpi-tile :label="__('customer_portal.insights.kpi_invoice_gross')" :value="$money($insights['gross'], $insights['currency'])" format="text" :hint="trans_choice('customer_portal.insights.kpi_invoice_count_hint', $insights['count'], ['count' => $insights['count']])" :href="route('customer.insights.index', ['year' => $year])" />
            <x-kpi-tile :label="__('customer_portal.insights.kpi_open')" :value="$money($insights['open'], $insights['currency'])" format="text" :tone="$insights['open'] > 0 ? 'warning' : 'success'" :href="route('customer.invoices.index', ['status' => 'open'])" />
        @endif
    </div>
</section>

{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auswertungen im Kundenportal (Phase 154, MVP-1242–1244). Erwartet: $customer, $year, $years,
     $time|null, $invoices|null, $billing|null, $tickets|null, $computedAt. Diagramme serverseitig
     als SVG mit gleichwertiger Tabelle (x-charts.*), Chart.js nur als Verbesserung; leere Serien
     zeigen den Leerzustand der Komponente. --}}
@extends('customer.layout')

@section('title', __('customer_portal.insights.title'))

@section('content')
    @php
        $hours = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
        $money = fn (float $amount, string $currency): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) . ' ' . $currency;
        $yearTabs = collect($years)->map(fn (int $y): array => [
            'label' => (string) $y,
            'href' => route('customer.insights.index', ['year' => $y]),
            'active' => $y === $year,
        ])->all();
        $hasSection = $time !== null || $invoices !== null || $billing !== null || $tickets !== null;
        $change = $time !== null && ($time['previous_minutes'] ?? null) !== null && $time['previous_minutes'] > 0
            ? round(($time['minutes'] - $time['previous_minutes']) / $time['previous_minutes'] * 100)
            : null;
    @endphp

    <x-page-toolbar :title="__('customer_portal.insights.title')" :subtitle="__('customer_portal.insights.subtitle')" class="mb-4" />

    @if (! $hasSection)
        <x-empty-state framed icon="insights" :title="__('customer_portal.insights.empty_all')" :message="__('customer_portal.insights.empty_all_hint')" />
    @else
        <div class="space-y-6">
            <x-tab-nav :items="$yearTabs" class="w-fit max-w-full flex-nowrap overflow-x-auto" aria-label="{{ __('customer_portal.insights.years_nav') }}" />

            @if ($time !== null)
                <section class="space-y-4" aria-labelledby="insights-time">
                    <h2 id="insights-time" class="flex items-center gap-2 text-lg font-semibold"><x-icon name="schedule" class="text-muted" />{{ __('customer_portal.insights.section_time') }}</h2>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <x-kpi-tile :label="__('customer_portal.insights.kpi_hours', ['year' => $year])" :value="$hours($time['minutes'])" format="text" tone="primary" :href="route('customer.time-entries.index', ['year' => $year])" />
                        <x-kpi-tile :label="__('customer_portal.insights.kpi_hours_prev', ['year' => $year - 1])" :value="$time['previous_minutes'] !== null ? $hours($time['previous_minutes']) : '—'" format="text"
                                    :hint="$change !== null ? __('customer_portal.insights.kpi_change', ['percent' => ($change > 0 ? '+' : '') . $change]) : null" />
                        <x-kpi-tile :label="__('customer_portal.time.kpi_entries')" :value="$time['entries']" />
                        <x-kpi-tile :label="__('customer_portal.time.kpi_projects')" :value="$time['projects']" />
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-charts.bar :title="__('customer_portal.insights.chart_hours_month')" :unit="__('customer_portal.insights.unit_hours')" :series="$time['monthly']"
                                      :y-label="__('customer_portal.insights.unit_hours')" :compare-label="__('customer_portal.insights.compare_prev', ['year' => $year - 1])"
                                      :computed-at="$computedAt" :note="__('customer_portal.insights.note_time')" />
                        <x-charts.bar-h :title="__('customer_portal.insights.chart_hours_project')" :unit="__('customer_portal.insights.unit_hours')" :series="$time['by_project']"
                                        :computed-at="$computedAt" :note="__('customer_portal.insights.note_time')" />
                    </div>
                    @if ($time['stacked'] !== null)
                        <x-charts.stacked-bar :title="__('customer_portal.insights.chart_hours_month_project')" :unit="__('customer_portal.insights.unit_hours')"
                                              :series="$time['stacked']['series']" :bands="$time['stacked']['bands']" :computed-at="$computedAt" />
                    @endif
                </section>
            @endif

            @if ($invoices !== null)
                <section class="space-y-4" aria-labelledby="insights-invoices">
                    <h2 id="insights-invoices" class="flex items-center gap-2 text-lg font-semibold"><x-icon name="receipt_long" class="text-muted" />{{ __('customer_portal.insights.section_invoices') }}</h2>
                    @forelse ($invoices['currencies'] as $block)
                        @if (count($invoices['currencies']) > 1)
                            <h3 class="text-base font-medium">{{ $block['currency'] }}</h3>
                        @endif
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <x-kpi-tile :label="__('customer_portal.insights.kpi_invoice_count', ['year' => $year])" :value="$block['count']" tone="primary"
                                        :hint="$block['credit'] > 0 ? __('customer_portal.insights.kpi_credit', ['amount' => $money($block['credit'], $block['currency'])]) : null"
                                        :href="route('customer.invoices.index', ['year' => $year])" />
                            <x-kpi-tile :label="__('customer_portal.insights.kpi_invoice_gross')" :value="$money($block['gross'], $block['currency'])" format="text" />
                            <x-kpi-tile :label="__('customer_portal.insights.kpi_open')" :value="$money($block['open'], $block['currency'])" format="text" :tone="$block['open'] > 0 ? 'warning' : 'success'"
                                        :hint="trans_choice('customer_portal.insights.kpi_open_count', $block['open_count'], ['count' => $block['open_count']])"
                                        :href="route('customer.invoices.index', ['status' => 'open'])" />
                            <x-kpi-tile :label="__('customer_portal.insights.kpi_overdue')" :value="$money($block['overdue'], $block['currency'])" format="text" :tone="$block['overdue_count'] > 0 ? 'error' : 'neutral'"
                                        :hint="trans_choice('customer_portal.insights.kpi_open_count', $block['overdue_count'], ['count' => $block['overdue_count']])" />
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <x-charts.bar :title="__('customer_portal.insights.chart_invoices_month')" :unit="$block['currency']" :series="$block['monthly']"
                                          :y-label="__('Rechnungen')" :y2-label="__('customer_portal.insights.credit_notes')" :computed-at="$computedAt" :note="__('customer_portal.insights.note_invoices')" />
                            <x-charts.line :title="__('customer_portal.insights.chart_open_trend')" :unit="$block['currency']" :series="$block['open_trend']"
                                           :computed-at="$computedAt" :note="__('customer_portal.insights.note_open_trend')" />
                        </div>
                        @if ($block['has_year_data'])
                            <x-card :title="__('customer_portal.insights.table_payment_state')" icon="payments" padding="p-0">
                                <x-table bare>
                                    <x-slot:head>
                                        <tr>
                                            <x-table.th>{{ __('Monat') }}</x-table.th>
                                            <x-table.th align="right">{{ __('customer_portal.insights.table_count') }}</x-table.th>
                                            <x-table.th align="right">{{ __('customer_portal.insights.table_gross') }}</x-table.th>
                                            <x-table.th align="right">{{ __('customer_portal.insights.credit_notes') }}</x-table.th>
                                            <x-table.th align="right">{{ __('customer_portal.insights.table_open') }}</x-table.th>
                                        </tr>
                                    </x-slot:head>
                                    @foreach ($block['table'] as $row)
                                        @continue($row['count'] === 0 && $row['credit'] <= 0)
                                        <tr>
                                            <td><a href="{{ $row['url'] }}" class="link link-hover">{{ $row['month'] }}</a></td>
                                            <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                                            <td class="text-right tabular-nums">{{ $money($row['gross'], $block['currency']) }}</td>
                                            <td class="text-right tabular-nums">{{ $row['credit'] > 0 ? $money($row['credit'], $block['currency']) : '—' }}</td>
                                            <td class="text-right tabular-nums">{{ $row['open'] > 0 ? $money($row['open'], $block['currency']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </x-table>
                            </x-card>
                        @endif
                    @empty
                        <x-empty-state framed icon="receipt_long" :title="__('customer_portal.insights.empty_invoices', ['year' => $year])" compact />
                    @endforelse
                </section>
            @endif

            @if ($billing !== null)
                <section class="space-y-4" aria-labelledby="insights-billing">
                    <h2 id="insights-billing" class="flex items-center gap-2 text-lg font-semibold"><x-icon name="account_balance_wallet" class="text-muted" />{{ __('customer_portal.insights.section_billing') }}</h2>
                    @if ($billing['last'] !== null)
                        @php($currency = $billing['last']['currency'] ?? '€')
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <x-kpi-tile :label="__('customer-billing.gross_value')" :value="$money($billing['last']['gross_value'], $currency)" format="text" :hint="$billing['last']['label']" :href="$billing['last']['url']" />
                            <x-kpi-tile :label="__('customer-billing.payments_total')" :value="$money($billing['last']['payments_total'], $currency)" format="text" tone="success" />
                            <x-kpi-tile :label="__('customer-billing.carry_in')" :value="$money($billing['last']['carry_in'], $currency)" format="text" />
                            <x-kpi-tile :label="__('customer-billing.balance')" :value="$money($billing['last']['balance'], $currency)" format="text" tone="primary"
                                        :hint="$billing['last']['locked'] ? null : __('customer-billing.provisional')" />
                        </div>
                    @endif
                    <x-charts.line :title="__('customer_portal.insights.chart_balance')" :unit="$billing['last']['currency'] ?? '€'" :series="$billing['series']"
                                   :computed-at="$computedAt" :note="__('customer_portal.insights.note_balance')" />
                </section>
            @endif

            @if ($tickets !== null)
                <section class="space-y-4" aria-labelledby="insights-tickets">
                    <h2 id="insights-tickets" class="flex items-center gap-2 text-lg font-semibold"><x-icon name="confirmation_number" class="text-muted" />{{ __('customer_portal.insights.section_tickets') }}</h2>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <x-kpi-tile :label="__('customer_portal.insights.kpi_opened', ['year' => $year])" :value="$tickets['opened']" tone="primary" :href="route('customer.tickets.index')" />
                        <x-kpi-tile :label="__('customer_portal.insights.kpi_resolved', ['year' => $year])" :value="$tickets['resolved']" tone="success" />
                        <x-kpi-tile :label="__('customer_portal.insights.kpi_avg_days')" :value="$tickets['average_days'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($tickets['average_days'], 1) : '—'" format="text"
                                    :hint="$tickets['average_days'] === null ? __('customer_portal.insights.avg_days_hint', ['min' => 5]) : null" />
                    </div>
                    <x-charts.stacked-bar :title="__('customer_portal.insights.chart_tickets')" :unit="__('Tickets')" :series="$tickets['series']" :bands="$tickets['bands']" :computed-at="$computedAt" />
                </section>
            @endif
        </div>
    @endif
@endsection

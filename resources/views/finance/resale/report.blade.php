{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Berichte (Feature 152, MVP-765) in drei Reitern: Marge je Produkt und je
  Rechnungsempfänger über die fälligen Perioden (Soll-Verkauf, berechnet laut
  Bezügen, Soll-/Ist-Einkauf), Verlängerungen im Zeitraum (30/60/90 Tage)
  und Abos ohne Rechnung älter als N Tage. Beträge je Währung, nie gemischt.
--}}
@extends('layouts.app')
@section('title', __('resale.report.title'))
@section('nav-title', __('resale.title.menu'))

@php
    $money = static fn(float $v, \CommonToolkit\Enums\CurrencyCode $c): string => \CommonToolkit\ValueObjects\Money::ofFloat($v, $c, 2)->format();
    $tabs = [
        ['label' => __('resale.report_tabs.margin'), 'route' => 'finance.resale.report.index', 'routeIs' => 'finance.resale.report.index', 'icon' => 'trending_up'],
        ['label' => __('resale.report_tabs.renewals'), 'route' => 'finance.resale.report.renewals', 'routeIs' => 'finance.resale.report.renewals', 'icon' => 'event_repeat'],
        ['label' => __('resale.report_tabs.unbilled'), 'route' => 'finance.resale.report.unbilled', 'routeIs' => 'finance.resale.report.unbilled', 'icon' => 'receipt_long'],
    ];
    $exportParams = match ($tab) {
        'margin' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        'renewals' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        default => ['days' => $days],
    };
    $exportRoute = match ($tab) {
        'margin' => 'finance.resale.report.margin.export',
        'renewals' => 'finance.resale.report.renewals.export',
        default => 'finance.resale.report.unbilled.export',
    };
    $subtitle = match ($tab) {
        'margin' => __('resale.margin.subtitle', ['from' => $from->fdate(), 'to' => $to->fdate()]),
        'renewals' => __('resale.renewals.subtitle', ['from' => $from->fdate(), 'to' => $to->fdate()]),
        default => __('resale.unbilled.subtitle', ['days' => $days]),
    };
@endphp

@section('content')
    <x-index-page :title="__('resale.report.title')" :subtitle="$subtitle">
        <x-slot:actions>
            <x-icon-btn icon="download" tone="ghost" size="sm" :href="route($exportRoute, $exportParams + ['format' => 'csv'])" show-label>{{ __('resale.margin.export_csv') }}</x-icon-btn>
            <x-icon-btn icon="table_view" tone="ghost" size="sm" :href="route($exportRoute, $exportParams + ['format' => 'xlsx'])" show-label>{{ __('resale.margin.export_xlsx') }}</x-icon-btn>
            @if ($tab === 'margin')
                <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm" :href="route($exportRoute, $exportParams + ['format' => 'pdf'])" show-label>{{ __('resale.margin.export_pdf') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="request_quote" tone="ghost" size="sm" :href="route('finance.resale.report.export')" show-label>{{ __('resale.export.action') }}</x-icon-btn>
            <x-icon-btn icon="request_quote" tone="ghost" size="sm" :href="route('finance.resale.report.export.xlsx')" show-label>{{ __('resale.export_files.xlsx_action') }}</x-icon-btn>
            <x-icon-btn icon="shopping_cart" tone="ghost" size="sm" :href="route('finance.resale.purchases.index')" show-label>{{ __('resale.purchase.title') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <x-tab-nav :items="$tabs" class="w-fit mb-4" />

        @if ($tab === 'margin')
            <x-filter-bar :action="route('finance.resale.report.index')" :reset="route('finance.resale.report.index')">
                <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to" from-id="report-from" to-id="report-to"
                              :from="$from->toDateString()" :to="$to->toDateString()" :from-label="__('resale.field.starts_on') . ' ' . __('Von')" :to-label="__('resale.field.starts_on') . ' ' . __('Bis')" />
            </x-filter-bar>

            @if ($report['mixed'])
                <div role="status" class="alert alert-info text-sm mb-4">
                    <span>{{ __('resale.margin.mixed_currencies', ['list' => implode(', ', array_map(static fn($c) => $c->value, $report['currencies']))]) }}</span>
                </div>
            @endif

            @foreach ([['title' => __('resale.report.by_product'), 'rows' => $report['by_product'], 'first' => __('resale.field.article')], ['title' => __('resale.report.by_recipient'), 'rows' => $report['by_recipient'], 'first' => __('resale.field.billed_to')]] as $block)
                <x-card :title="$block['title']" padding="p-0" class="mb-4">
                    <x-table bare table-sort="client">
                        <x-slot:head>
                            <tr>
                                <x-table.th sort type="string">{{ $block['first'] }}</x-table.th>
                                <x-table.th sort type="string">{{ __('resale.margin.currency') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.periods') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.open') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.expected_sale') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.billed') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.expected_purchase') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.actual_purchase') }}</x-table.th>
                                <x-table.th class="text-right" sort type="number">{{ __('resale.report.margin') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($block['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-xs text-muted">{{ $row['currency']->value }}</td>
                                <td class="text-right tabular-nums">{{ $row['periods'] }}</td>
                                <td class="text-right tabular-nums {{ $row['open'] > 0 ? 'text-error' : '' }}">{{ $row['open'] }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['expected_sale'], $row['currency']) }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['billed'], $row['currency']) }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $money($row['expected_purchase'], $row['currency']) }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $row['with_actual'] > 0 ? $money($row['actual_purchase'], $row['currency']) : '—' }}@if ($row['with_actual'] > 0 && $row['with_actual'] < $row['periods'])<span class="block text-xs text-muted">{{ $row['with_actual'] }}/{{ $row['periods'] }}</span>@endif</td>
                                <td class="text-right tabular-nums whitespace-nowrap {{ $row['margin'] < 0 ? 'text-error' : 'text-success' }}">{{ $money($row['margin'], $row['currency']) }}</td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="9" :title="__('resale.report.empty')" compact />
                        @endforelse
                    </x-table>
                </x-card>
            @endforeach
            <p class="text-xs text-muted">{{ __('resale.report.hint') }}</p>
        @elseif ($tab === 'renewals')
            <div class="grid grid-cols-3 gap-3 mb-4">
                @foreach ($buckets as $bucketDays => $count)
                    <x-kpi-tile :label="__('resale.renewals.bucket', ['days' => $bucketDays])" :value="$count" :tone="$count > 0 ? 'warning' : 'neutral'"
                                :href="route('finance.resale.report.renewals', ['from' => $today->toDateString(), 'to' => $today->addDays($bucketDays)->toDateString()])"
                                :active="$from->isSameDay($today) && $to->isSameDay($today->addDays($bucketDays))" />
                @endforeach
            </div>
            <x-filter-bar :action="route('finance.resale.report.renewals')" :reset="route('finance.resale.report.renewals')">
                <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to" from-id="renewals-from" to-id="renewals-to"
                              :from="$from->toDateString()" :to="$to->toDateString()" />
            </x-filter-bar>
            <p class="text-xs text-muted mb-2">{{ __('resale.renewals.hint') }}</p>
            <x-table :zebra="true" table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date">{{ __('resale.renewals.date') }}</x-table.th>
                        <x-table.th>{{ __('resale.renewals.mode') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('resale.field.billed_to') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                        <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.interval') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($rows as $row)
                    @php $subscription = $row['subscription']; @endphp
                    <tr class="hover">
                        <td class="whitespace-nowrap tabular-nums">{{ $row['date']->fdate() }}<span class="block text-xs text-muted">{{ $row['days'] === 0 ? __('resale.renewals.today') : trans_choice('resale.renewals.in_days', $row['days'], ['days' => $row['days']]) }}</span></td>
                        <td><x-status-badge size="xs" :tone="$row['mode'] === 'ends' ? 'error' : 'info'" :label="__('resale.renewals.mode_' . $row['mode'])" /></td>
                        <td><a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>@if ($subscription->productLabel() !== null)<span class="block text-xs text-muted">{{ $subscription->productLabel() }}</span>@endif</td>
                        <td class="text-sm">{{ $subscription->holderLabel() }}</td>
                        <td class="text-sm">{{ $subscription->billedTo()?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $subscription->provider->label() }}</td>
                        <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                        <td class="text-sm">{{ $subscription->interval->label() }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" icon="event_repeat" :title="__('resale.renewals.empty')" compact />
                @endforelse
            </x-table>
        @else
            <x-filter-bar :action="route('finance.resale.report.unbilled')" :reset="route('finance.resale.report.unbilled')">
                <x-filter-field :label="__('resale.unbilled.days_label')" for="unbilled-days" inline>
                    <input type="number" id="unbilled-days" name="days" value="{{ $days }}" min="0" max="3650" step="1" class="input input-sm input-bordered w-28 shrink-0">
                </x-filter-field>
            </x-filter-bar>
            <p class="text-xs text-muted mb-2">{{ __('resale.unbilled.hint') }}</p>
            <x-table :zebra="true" table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('resale.field.billed_to') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('resale.unbilled.oldest') }}</x-table.th>
                        <x-table.th class="text-right" sort type="number">{{ __('resale.unbilled.days') }}</x-table.th>
                        <x-table.th class="text-right" sort type="number">{{ __('resale.unbilled.open_periods') }}</x-table.th>
                        <x-table.th class="text-right" sort type="number">{{ __('resale.export.open_amount') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($rows as $row)
                    @php $subscription = $row['subscription']; @endphp
                    <tr class="hover">
                        <td><a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a></td>
                        <td class="text-sm">{{ $subscription->holderLabel() }}</td>
                        <td class="text-sm">{{ $subscription->billedTo()?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $subscription->provider->label() }}</td>
                        <td class="whitespace-nowrap tabular-nums text-sm">{{ $row['oldest']->label() }}</td>
                        <td class="text-right tabular-nums text-error">{{ $row['days'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['open_periods'] }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ $row['open_amount']?->format() ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" icon="task_alt" :title="__('resale.unbilled.empty', ['days' => $days])" compact />
                @endforelse
            </x-table>
        @endif
    </x-index-page>
@endsection

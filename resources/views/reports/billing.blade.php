@extends('layouts.app')
@section('title', __('Billing'))
@section('nav-title', __('Billing-Auswertung'))

@section('content')
@php
    $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    $fmtMin = function (int $minutes): string {
        $abs = abs($minutes);
        return intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
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

    <x-filter-bar :action="route('reports.billing')" :reset="route('reports.billing')">
        <x-slot:extra>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.billing', ['export' => 'csv'])"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.billing', ['export' => 'pdf'])"
                        show-label>PDF</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Ausgestellt + Bezahlt (Σ Brutto)') }}</div>
            <div class="stat-value text-2xl">{{ $eur($totalIssuedPaid) }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Offene Forderungen') }}</div>
            <div class="stat-value text-2xl">{{ $eur($aging['open_total']) }}</div>
            <div class="stat-desc {{ $aging['buckets']['30_plus']['count'] > 0 ? 'text-error' : '' }}">
                {{ $aging['buckets']['30_plus']['count'] }} {{ __('> 30 Tage') }}
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Unbillte Zeit') }}</div>
            <div class="stat-value text-2xl">{{ $fmtMin($unbilled['minutes']) }}</div>
            <div class="stat-desc">{{ $unbilled['count'] }} {{ __('Einträge') }} · {{ $eur($unbilled['projected_revenue']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
    </div>
</x-page-shell>
@endsection

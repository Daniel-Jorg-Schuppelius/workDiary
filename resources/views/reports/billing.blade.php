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
            <a href="{{ route('reports.billing', ['export' => 'csv']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.billing', ['export' => 'pdf']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
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
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Anzahl') }}</th>
                            <th class="text-right">{{ __('Netto') }}</th>
                            <th class="text-right">{{ __('Brutto') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($status as $st => $s)
                            <tr>
                                <td>{{ $statusLabels[$st] ?? $st }}</td>
                                <td class="text-right tabular-nums">{{ $s['count'] }}</td>
                                <td class="text-right tabular-nums">{{ $eur($s['subtotal']) }}</td>
                                <td class="text-right tabular-nums">{{ $eur($s['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Aging – offene Posten') }}</h3>
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Bucket') }}</th>
                            <th class="text-right">{{ __('Anzahl') }}</th>
                            <th class="text-right">{{ __('Summe') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($aging['buckets'] as $k => $b)
                            <tr class="{{ $k === '30_plus' && $b['count'] > 0 ? 'text-error font-semibold' : '' }}">
                                <td>{{ $agingLabels[$k] ?? $k }}</td>
                                <td class="text-right tabular-nums">{{ $b['count'] }}</td>
                                <td class="text-right tabular-nums">{{ $eur($b['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Offen gesamt') }}</td>
                            <td></td>
                            <td class="text-right tabular-nums">{{ $eur($aging['open_total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Top-Kunden (ausgestellt + bezahlt im Zeitraum)') }}</h3>
        @if (empty($perCustomer))
            <x-empty-state :title="__('Keine Rechnungen im Zeitraum.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Kunde') }}</th>
                            <th class="text-right">{{ __('Rechnungen') }}</th>
                            <th class="text-right">{{ __('Brutto') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perCustomer as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['customer']->name }}</td>
                                <td class="text-right tabular-nums">{{ $r['count'] }}</td>
                                <td class="text-right tabular-nums">{{ $eur($r['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection

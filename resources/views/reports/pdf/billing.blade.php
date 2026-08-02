@extends('reports.pdf.layout')

@section('pdf-title', 'Billing – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Abrechnungs-Auswertung'))

@push('pdf-styles')
<style>
    .grid { width: 100%; }
    .grid td.col { border: 0; width: 50%; vertical-align: top; padding: 0 6px 0 0; }
    .warn { color: #b30000; font-weight: bold; }
</style>
@endpush

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $eur = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
        $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration(abs($minutes));
        $totalIssuedPaid = ($status['issued']['total'] ?? 0) + ($status['paid']['total'] ?? 0);
    @endphp

    @include('reports.pdf.charts._chart')

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Ausgestellt + Bezahlt') }}</div><div class="value">{{ $eur($totalIssuedPaid) }}</div></td>
            <td><div class="label">{{ __('Offene Forderungen') }}</div><div class="value">{{ $eur($aging['open_total']) }}</div></td>
            <td><div class="label">&gt; 30 Tage</div><div class="value {{ $aging['buckets']['30_plus']['count'] > 0 ? 'warn' : '' }}">{{ $aging['buckets']['30_plus']['count'] }} ({{ $eur($aging['buckets']['30_plus']['total']) }})</div></td>
            <td><div class="label">{{ __('Unbillte Zeit') }}</div><div class="value">{{ $fmtMin($unbilled['minutes']) }} · {{ $eur($unbilled['projected_revenue']) }}</div></td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="col">
                <h2>{{ __('Rechnungen nach Status') }}</h2>
                <table class="data">
                    <thead><tr><th>Status</th><th class="right">Anzahl</th><th class="right">Netto</th><th class="right">Brutto</th></tr></thead>
                    <tbody>
                        @foreach ($status as $st => $s)
                            <tr>
                                <td>{{ __("values.$st") }}</td>
                                <td class="right">{{ $s['count'] }}</td>
                                <td class="right">{{ $eur($s['subtotal']) }}</td>
                                <td class="right">{{ $eur($s['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="col">
                <h2>{{ __('Aging – offene Posten') }}</h2>
                <table class="data">
                    <thead><tr><th>Bucket</th><th class="right">Anzahl</th><th class="right">Summe</th></tr></thead>
                    <tbody>
                        @foreach ($aging['buckets'] as $k => $b)
                            <tr class="{{ $k === '30_plus' && $b['count'] > 0 ? 'warn' : '' }}">
                                <td>{{ $k }}</td>
                                <td class="right">{{ $b['count'] }}</td>
                                <td class="right">{{ $eur($b['total']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="totals"><td>Offen gesamt</td><td></td><td class="right">{{ $eur($aging['open_total']) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Top-Kunden (ausgestellt + bezahlt)</h2>
    <table class="data">
        <thead><tr><th>Kunde</th><th class="right">Rechnungen</th><th class="right">Brutto</th></tr></thead>
        <tbody>
            @forelse ($perCustomer as $r)
                <tr>
                    <td>{{ $r['customer']->name }}</td>
                    <td class="right">{{ $r['count'] }}</td>
                    <td class="right">{{ $eur($r['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center; padding:12px; color:#888;">{{ __('Keine Rechnungen im Zeitraum.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

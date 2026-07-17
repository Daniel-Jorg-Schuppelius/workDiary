@extends('reports.pdf.layout')

@section('pdf-title', 'Materialien – ' . $from . ' bis ' . $to)
@section('pdf-heading', 'Materialverbrauch')

@push('pdf-styles')
<style>
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
</style>
@endpush

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
        $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Materialien</div><div class="value">{{ $totals['materials'] }}</div></td>
            <td><div class="label">Verwendungen</div><div class="value">{{ $totals['usage_count'] }}</div></td>
            <td><div class="label">Netto Σ</div><div class="value">{{ $eur($totals['line_total_net']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Material</th>
                <th>Einheit</th>
                <th class="right">Menge</th>
                <th class="right">Verw.</th>
                <th class="right">Netto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="mono">{{ $r['sku'] ?? '—' }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['unit'] }}</td>
                    <td class="right">{{ $num($r['quantity'], 3) }}</td>
                    <td class="right">{{ $r['usage_count'] }}</td>
                    <td class="right">{{ $eur($r['line_total_net']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; padding:12px; color:#888;">{{ __('Keine Daten im Zeitraum.') }}</td></tr>
            @endforelse
            @if (! empty($rows))
                <tr class="totals">
                    <td colspan="4">Gesamt</td>
                    <td class="right">{{ $totals['usage_count'] }}</td>
                    <td class="right">{{ $eur($totals['line_total_net']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection

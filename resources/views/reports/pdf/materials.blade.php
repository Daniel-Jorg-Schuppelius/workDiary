{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : materials.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Materialien') . ' – ' . $from . ' ' . __('bis') . ' ' . $to)
@section('pdf-heading', __('Materialverbrauch'))

@push('pdf-styles')
<style>
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Bereich') }}: {{ $scope === 'team' ? __('Gesamtes Team') : __('Eigene') }} ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        $num = fn (float $v, int $d = 2) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, $d, withThousandsSeparator: true);
        $eur = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Materialien') }}</div><div class="value">{{ $totals['materials'] }}</div></td>
            <td><div class="label">{{ __('Verwendungen') }}</div><div class="value">{{ $totals['usage_count'] }}</div></td>
            <td><div class="label">{{ __('Netto Σ') }}</div><div class="value">{{ $eur($totals['line_total_net']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>{{ __('Material') }}</th>
                <th>{{ __('Einheit') }}</th>
                <th class="right">{{ __('Menge') }}</th>
                <th class="right">{{ __('Verwendungen') }}</th>
                <th class="right">{{ __('Netto') }}</th>
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
                    <td colspan="4">{{ __('Gesamt') }}</td>
                    <td class="right">{{ $totals['usage_count'] }}</td>
                    <td class="right">{{ $eur($totals['line_total_net']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
